/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { nextTick } from 'vue'

/** Duration (in ms) of the travel of a tile between the stripe and its slot. */
const TRANSITION_DURATION = 300

/**
 * Data attribute the animated tiles are looked up by. Both the stripe and the
 * speakers grid tag their tiles with it.
 */
export const TILE_SESSION_ATTRIBUTE = 'data-tile-session-id'

/**
 * @param sessionId - session id of the tile to look up
 */
function findTile(sessionId: string): HTMLElement | null {
	return document.querySelector(`[${TILE_SESSION_ATTRIBUTE}="${CSS.escape(sessionId)}"]`)
}

/**
 * Kept around rather than queried again every time, as its `matches` already
 * follows the setting when the user changes it mid-call. Only built on the
 * first animation, so that importing this module does not require a document.
 */
let reducedMotionQuery: MediaQueryList | undefined

/**
 * Whether the user asked not to be shown motion.
 */
function prefersReducedMotion(): boolean {
	reducedMotionQuery ??= window.matchMedia('(prefers-reduced-motion: reduce)')
	return reducedMotionQuery.matches
}

/**
 * Move a tile from where it was to where it now is.
 *
 * The tile is rendered by a different grid before and after the change, so it
 * cannot simply be transitioned: it is a new element in a new place. It is
 * instead put back over the old one with a transform and then released, which
 * reads as a single tile travelling to its new spot.
 *
 * @param sessionId - session id of the tile to animate
 * @param from - bounding rectangle the tile had before the change
 */
function animateTile(sessionId: string, from: DOMRect) {
	const tile = findTile(sessionId)
	if (!tile) {
		return
	}

	const to = tile.getBoundingClientRect()
	if (!from.width || !from.height || !to.width || !to.height) {
		// One of the two grids was not laid out, there is nothing to travel
		// between
		return
	}

	if (from.left === to.left && from.top === to.top
		&& from.width === to.width && from.height === to.height) {
		// The tile did not move, so no transition would start and the styles
		// applied below would never be handed back
		return
	}

	/**
	 * Hand the tile back to the layout.
	 *
	 * @param event - the transition that ended, when called as a listener
	 */
	function reset(event?: TransitionEvent) {
		// Tiles hold overlays and buttons transitioning on their own, and those
		// events bubble up to here
		if (event && (event.target !== tile || event.propertyName !== 'transform')) {
			return
		}

		tile!.style.transition = ''
		tile!.style.transform = ''
		tile!.style.transformOrigin = ''
		tile!.style.pointerEvents = ''
		tile!.removeEventListener('transitionend', reset)
		tile!.removeEventListener('transitioncancel', reset)
	}

	tile.style.transition = 'none'
	tile.style.transformOrigin = 'top left'
	tile.style.transform = `translate(${from.left - to.left}px, ${from.top - to.top}px)`
		+ ` scale(${from.width / to.width}, ${from.height / to.height})`
	// A tile on its way is not a target: it would be hovered where it is only
	// passing by, and clicked while no longer under the cursor
	tile.style.pointerEvents = 'none'

	// Apply the offset before transitioning it away, otherwise the tile is
	// simply painted at its new place
	tile.offsetWidth

	tile.addEventListener('transitionend', reset)
	// Nothing else ends the transition when it is taken over, for instance by
	// another change moving the tile again
	tile.addEventListener('transitioncancel', reset)
	tile.style.transition = `transform ${TRANSITION_DURATION}ms ease-in-out`
	tile.style.transform = ''
}

/**
 * Animates the tiles entering or leaving the main area, so that a promoted
 * speaker is seen travelling from the stripe into its slot rather than
 * appearing there, and the other way around once it is demoted.
 *
 * The speakers that keep their spot travel as well: they make room for the
 * newcomer, or take back the space of whoever left, at the same time and pace,
 * so the main area is seen rearranging itself as a whole.
 *
 * Must be called while the tiles are still in their old place, that is from a
 * watcher with the default `pre` flush, before the grids are re-rendered.
 *
 * @param sessionIds - session ids of the tiles of the main area
 * @param previousSessionIds - the same, before the change
 */
export function animateTilePromotion(sessionIds: string[], previousSessionIds: string[]) {
	if (prefersReducedMotion()) {
		return
	}

	// Everyone in the main area before or after the change: those coming in,
	// those going out, and those simply resized by the new layout
	const involved = new Set([...previousSessionIds, ...sessionIds])

	const origins = new Map<string, DOMRect>()
	involved.forEach((sessionId) => {
		const rect = findTile(sessionId)?.getBoundingClientRect()
		if (rect) {
			origins.set(sessionId, rect)
		}
	})

	if (!origins.size) {
		return
	}

	nextTick(() => {
		origins.forEach((rect, sessionId) => animateTile(sessionId, rect))
	})
}
