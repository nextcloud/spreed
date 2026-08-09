/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'

import { computed, onScopeDispose, ref, watch } from 'vue'

/** Time (in ms) a participant has to speak without interruption to be promoted. */
export const PROMOTE_DELAY = 3_000

/** Time (in ms) a promoted participant stays silent before being demoted again. */
export const DEMOTE_DELAY = 30_000

/** Maximum number of speakers promoted at the same time. */
export const MAX_PROMOTED_SPEAKERS = 3

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
 * At most {@link MAX_PROMOTED_SPEAKERS} speakers are promoted at a time. Once
 * they are all taken, a new speaker takes the spot of whoever spoke least
 * recently, so that the main area always follows the conversation rather than
 * making a new speaker wait for a spot to be given up.
 *
 * @param options - the reactive inputs driving the tracking
 * @param options.callParticipantModels - all call participant models
 * @param options.enabled - whether speakers should be tracked at all
 */
export function useActiveSpeakers({
	callParticipantModels,
	enabled,
}: UseActiveSpeakersOptions) {
	// Session ids of the participants that are currently promoted, in the order
	// they were promoted. Only ids are kept, so no participant model is
	// referenced here.
	const activeSessionIds = ref<string[]>([])

	// When each participant last took part in the conversation, used to pick who
	// makes room when a new speaker comes in.
	const lastSpokeAt: Record<string, number> = {}

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
	 * @param sessionId session id of the participant to drop
	 */
	function deactivate(sessionId: string) {
		const index = activeSessionIds.value.indexOf(sessionId)
		if (index !== -1) {
			activeSessionIds.value.splice(index, 1)
		}
	}

	/**
	 * Promote a participant, taking the spot of whoever spoke least recently if
	 * they are all taken.
	 *
	 * A participant that is speaking right now counts as speaking at this very
	 * moment, so somebody in the middle of a sentence is only ever replaced when
	 * everyone promoted is talking at once.
	 *
	 * @param sessionId - session id of the participant to promote
	 * @param speaking - session ids of the participants speaking right now
	 */
	function activate(sessionId: string, speaking: Set<string>) {
		if (activeSessionIds.value.includes(sessionId)) {
			return
		}

		if (activeSessionIds.value.length >= MAX_PROMOTED_SPEAKERS) {
			const now = Date.now()
			const recencyOf = (id: string) => (speaking.has(id) ? now : (lastSpokeAt[id] ?? 0))
			const leastRecent = activeSessionIds.value
				.reduce((least, id) => (recencyOf(id) < recencyOf(least) ? id : least))

			clearTimer(demoteTimers, leastRecent)
			deactivate(leastRecent)
		}

		activeSessionIds.value.push(sessionId)
	}

	/**
	 * Forget every speaker and cancel all pending changes.
	 */
	function reset() {
		Object.keys(promoteTimers).forEach((sessionId) => clearTimer(promoteTimers, sessionId))
		Object.keys(demoteTimers).forEach((sessionId) => clearTimer(demoteTimers, sessionId))
		Object.keys(lastSpokeAt).forEach((sessionId) => delete lastSpokeAt[sessionId])
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

	// TODO: extract one session-keyed set to be used by tile ordering too
	watch(speakingSessionIds, (sessionIds, previousSessionIds) => {
		const speaking = new Set(sessionIds)

		sessionIds.forEach((sessionId) => {
			// Speaking again: whatever was pending for this participant is moot
			clearTimer(demoteTimers, sessionId)
			lastSpokeAt[sessionId] = Date.now()

			if (activeSessionIds.value.includes(sessionId) || promoteTimers[sessionId]) {
				return
			}

			promoteTimers[sessionId] = setTimeout(() => {
				delete promoteTimers[sessionId]
				activate(sessionId, new Set(speakingSessionIds.value))
			}, PROMOTE_DELAY)
		})

		previousSessionIds.filter((sessionId) => !speaking.has(sessionId)).forEach((sessionId) => {
			// Stopped speaking before being promoted, so it never gets promoted
			clearTimer(promoteTimers, sessionId)
			// They took part in the conversation up to this very moment
			lastSpokeAt[sessionId] = Date.now()

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
	// would keep an empty tile around.
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
			delete lastSpokeAt[sessionId]
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
		promotedSessionIds: computed(() => [...activeSessionIds.value]),
	}
}
