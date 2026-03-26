/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Conversation, Participant } from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useStore } from 'vuex'
import { ATTENDEE, CALL, CONVERSATION, PARTICIPANT } from '../constants.ts'
import { callSIPDialOut } from '../services/callsService.ts'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { useActorStore } from '../stores/actor.ts'
import { isAxiosErrorResponse } from '../types/guards.ts'

/**
 * Handler function to join a call and manage side effects
 */
export function useJoinCall() {
	const actorStore = useActorStore()
	const vuexStore = useStore()

	/**
	 * Returns whether the conversation is a phone room (with a single SIP phone participant)
	 *
	 * @param conversation - conversation object
	 * @param conversation.objectId - conversation objectId
	 * @param conversation.objectType - conversation objectType
	 */
	function isConversationPhoneRoom({ objectId, objectType }: Conversation) {
		return objectId === CONVERSATION.OBJECT_ID.PHONE_OUTGOING
			&& [
				CONVERSATION.OBJECT_TYPE.PHONE_LEGACY,
				CONVERSATION.OBJECT_TYPE.PHONE_PERSISTENT,
				CONVERSATION.OBJECT_TYPE.PHONE_TEMPORARY,
			].includes(objectType)
	}

	/**
	 * Tries to call the given SIP phone participant
	 *
	 * @param token - conversation token of where to join
	 * @param attendeeId - id of the phone participant
	 */
	async function dialOutPhoneNumber(token: string, attendeeId: number) {
		try {
			await callSIPDialOut(token, attendeeId)
		} catch (exception) {
			if (isAxiosErrorResponse<{ message: string }>(exception) && exception.response?.data?.ocs?.data?.message) {
				showError(t('spreed', 'Phone number could not be called: {error}', {
					error: exception.response.data.ocs.data.message,
				}))
			} else {
				console.error(exception)
				showError(t('spreed', 'Phone number could not be called'))
			}
		}
	}

	/**
	 * Starts or joins a call
	 *
	 * @param token - conversation token of where to join
	 * @param options - joining options
	 * @param options.silent - whether to join the call silently (no notifications)
	 * @param options.recordingConsent - whether to join the call with recording consent
	 * @param options.shouldStartRecording - whether to start the recording together with the call (requires recording backend)
	 */
	async function joinCall(token: string, {
		silent = false,
		recordingConsent = false,
		shouldStartRecording = false,
	} = {}) {
		const conversation = vuexStore.getters.conversation(token)
		if (!actorStore.participantIdentifier.sessionId || conversation.attendeeId !== actorStore.participantIdentifier.attendeeId) {
			console.error('Trying to join call without having joined the conversation')
			return
		}

		const isPhoneRoom = isConversationPhoneRoom(conversation)

		// Define flags to join with (just call / with audio / with video)
		let flags = PARTICIPANT.CALL_FLAG.IN_CALL
		if (conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
		}
		if (conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO && !isPhoneRoom) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
		}

		// Close MediaSettings
		emit('talk:media-settings:hide')
		// Close navigation when joining the call
		emit('toggle-navigation', { open: false })

		console.debug('Joining call')
		await vuexStore.dispatch('joinCall', {
			token,
			participantIdentifier: actorStore.participantIdentifier,
			flags,
			silent,
			recordingConsent,
		})

		if (shouldStartRecording && getTalkConfig(token, 'call', 'recording')) {
			// Do not wait for async operation
			vuexStore.dispatch('startCallRecording', {
				token,
				callRecording: CALL.RECORDING.VIDEO,
			})
		}

		if (isPhoneRoom) {
			const attendeeId = vuexStore.getters.participantsList(token)
				.find((participant: Participant) => participant.actorType === ATTENDEE.ACTOR_TYPE.PHONES)
				?.attendeeId
			if (attendeeId) {
				// Do not wait for async operation
				dialOutPhoneNumber(token, attendeeId)
			}
		}
	}

	return {
		joinCall,
	}
}
