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
 * Whether the user asked not to be shown motion.
 */
function prefersReducedMotion(): boolean {
	return window.matchMedia('(prefers-reduced-motion: reduce)').matches
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

	/**
	 * Hand the tile back to the layout.
	 */
	function reset() {
		tile!.style.transition = ''
		tile!.style.transform = ''
		tile!.style.transformOrigin = ''
		tile!.removeEventListener('transitionend', reset)
	}

	tile.style.transition = 'none'
	tile.style.transformOrigin = 'top left'
	tile.style.transform = `translate(${from.left - to.left}px, ${from.top - to.top}px)`
		+ ` scale(${from.width / to.width}, ${from.height / to.height})`

	// Apply the offset before transitioning it away, otherwise the tile is
	// simply painted at its new place
	tile.offsetWidth

	tile.addEventListener('transitionend', reset)
	tile.style.transition = `transform ${TRANSITION_DURATION}ms ease-in-out`
	tile.style.transform = ''
}

/**
 * Animates the tiles entering or leaving the main area, so that a promoted
 * speaker is seen travelling from the stripe into its slot rather than
 * appearing there, and the other way around once it is demoted.
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

	const inMainArea = new Set(sessionIds)
	const wasInMainArea = new Set(previousSessionIds)
	const moved = [
		...sessionIds.filter((sessionId) => !wasInMainArea.has(sessionId)),
		...previousSessionIds.filter((sessionId) => !inMainArea.has(sessionId)),
	]

	const origins = new Map<string, DOMRect>()
	moved.forEach((sessionId) => {
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
