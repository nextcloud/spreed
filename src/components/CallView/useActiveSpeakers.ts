/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'

import { computed, onScopeDispose, ref, watch } from 'vue'

/** Time (in ms) a participant has to speak without interruption to be promoted. */
export const PROMOTE_DELAY = 3000

/** Time (in ms) a promoted participant stays silent before being demoted again. */
export const DEMOTE_DELAY = 60000

/** Maximum number of speakers promoted at the same time. */
export const MAX_PROMOTED_SPEAKERS = 4

/**
 * Minimal shape of a call participant model the speaker tracking relies on.
 * The real models expose many more attributes, but only these are read here.
 */
export type SpeakerModel = {
	attributes: {
		nextcloudSessionId: string
		speaking?: boolean
	}
}

type UseActiveSpeakersOptions = {
	/** All call participant models, in their source order */
	callParticipantModels: Readonly<Ref<SpeakerModel[]>>
	/** Whether speakers should be tracked at all */
	enabled: Readonly<Ref<boolean>>
}

/**
 * Tracks which participants are active enough to deserve a spot in the main
 * area of the call view.
 *
 * A participant that speaks for {@link PROMOTE_DELAY} without interruption
 * becomes active, and stops being active once it has been silent for
 * {@link DEMOTE_DELAY}. Short interjections therefore never disturb the view,
 * and someone who only listens for a while makes room again on their own.
 *
 * The active speakers are kept in the order they became active and the promoted
 * ones are simply the first {@link MAX_PROMOTED_SPEAKERS} of them. A fifth
 * speaker is thus not dropped but queued: it slides into the view as soon as one
 * of the speakers ahead of it is demoted, which keeps the promoted tiles stable
 * instead of letting a single word evict someone mid-sentence.
 *
 * @param options - the reactive inputs driving the tracking
 * @param options.callParticipantModels - all call participant models
 * @param options.enabled - whether speakers should be tracked at all
 */
export function useActiveSpeakers({
	callParticipantModels,
	enabled,
}: UseActiveSpeakersOptions) {
	// Session ids of the participants that are currently active, in the order
	// they became active. Only ids are kept, so no participant model is
	// referenced here.
	const activeSessionIds = ref<string[]>([])

	const promoteTimers: Record<string, ReturnType<typeof setTimeout>> = {}
	const demoteTimers: Record<string, ReturnType<typeof setTimeout>> = {}

	/**
	 * @param timers the timer map to clear the entry from
	 * @param sessionId session id of the participant
	 */
	function clearTimer(timers: Record<string, ReturnType<typeof setTimeout>>, sessionId: string) {
		clearTimeout(timers[sessionId])
		delete timers[sessionId]
	}

	/**
	 * @param sessionId session id of the participant to make active
	 */
	function activate(sessionId: string) {
		if (!activeSessionIds.value.includes(sessionId)) {
			activeSessionIds.value.push(sessionId)
		}
	}

	/**
	 * @param sessionId session id of the participant to drop
	 */
	function deactivate(sessionId: string) {
		const index = activeSessionIds.value.indexOf(sessionId)
		if (index !== -1) {
			activeSessionIds.value.splice(index, 1)
		}
	}

	/**
	 * Forget every speaker and cancel all pending changes.
	 */
	function reset() {
		Object.keys(promoteTimers).forEach((sessionId) => clearTimer(promoteTimers, sessionId))
		Object.keys(demoteTimers).forEach((sessionId) => clearTimer(demoteTimers, sessionId))
		activeSessionIds.value = []
	}

	const speakingSessionIds = computed(() => {
		if (!enabled.value) {
			return []
		}

		return callParticipantModels.value
			.filter((model) => model.attributes.speaking)
			.map((model) => model.attributes.nextcloudSessionId)
	})

	watch(speakingSessionIds, (sessionIds, previousSessionIds) => {
		const speaking = new Set(sessionIds)

		sessionIds.forEach((sessionId) => {
			// Speaking again: whatever was pending for this participant is moot
			clearTimer(demoteTimers, sessionId)

			if (activeSessionIds.value.includes(sessionId) || promoteTimers[sessionId]) {
				return
			}

			promoteTimers[sessionId] = setTimeout(() => {
				delete promoteTimers[sessionId]
				activate(sessionId)
			}, PROMOTE_DELAY)
		})

		previousSessionIds.filter((sessionId) => !speaking.has(sessionId)).forEach((sessionId) => {
			// Stopped speaking before being promoted, so it never gets promoted
			clearTimer(promoteTimers, sessionId)

			if (!activeSessionIds.value.includes(sessionId) || demoteTimers[sessionId]) {
				return
			}

			demoteTimers[sessionId] = setTimeout(() => {
				delete demoteTimers[sessionId]
				deactivate(sessionId)
			}, DEMOTE_DELAY)
		})
	})

	// A participant that left frees its spot right away: `speaking` is not
	// guaranteed to be reset on disconnection, so waiting for the silence timer
	// would keep an empty tile around for a minute.
	watch(() => callParticipantModels.value.map((model) => model.attributes.nextcloudSessionId), (sessionIds) => {
		const present = new Set(sessionIds)
		const known = new Set([
			...activeSessionIds.value,
			...Object.keys(promoteTimers),
			...Object.keys(demoteTimers),
		])

		known.forEach((sessionId) => {
			if (present.has(sessionId)) {
				return
			}
			clearTimer(promoteTimers, sessionId)
			clearTimer(demoteTimers, sessionId)
			deactivate(sessionId)
		})
	})

	watch(enabled, (value) => {
		if (!value) {
			reset()
		}
	})

	onScopeDispose(reset)

	return {
		/** Session ids of the speakers to show in the main area, most senior first */
		promotedSessionIds: computed(() => activeSessionIds.value.slice(0, MAX_PROMOTED_SPEAKERS)),
	}
}
