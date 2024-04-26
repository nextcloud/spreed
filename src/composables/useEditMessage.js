/*
 * @copyright Copyright (c) 2024 Dorra Jaouad <dorra.jaoued1@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { computed } from 'vue'

import { getCapabilities } from '@nextcloud/capabilities'
import moment from '@nextcloud/moment'

import { useStore } from './useStore.js'
import { CONVERSATION, PARTICIPANT } from '../constants.js'

/**
 * Check whether the user can edit the message or not
 *
 * @param {string} token conversation token
 * @param {string} messageId message id to edit
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export function useEditMessage(token, messageId) {
	const store = useStore()

	// Get the conversation and message
	const conversation = computed(() => store.getters.conversation(token))
	const message = computed(() => store.getters.message(token, messageId))

	const isConversationReadOnly = computed(() => conversation.value.readOnly === CONVERSATION.STATE.READ_ONLY)

	const isModifiable = computed(() =>
		!isConversationReadOnly.value
        && conversation.value.participantType !== PARTICIPANT.TYPE.GUEST)

	const isObjectShare = computed(() => Object.keys(Object(message.value.messageParameters)).some(key => key.startsWith('object')))

	const isMyMsg = computed(() =>
		message.value.actorId === store.getters.getActorId()
        && message.value.actorType === store.getters.getActorType()
	)

	const isOneToOne = computed(() =>
		conversation.value.type === CONVERSATION.TYPE.ONE_TO_ONE
        || conversation.value.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
	)

	const canEditMessage = getCapabilities()?.spreed?.features?.includes('edit-messages')

	return computed(() => {
		if (!canEditMessage || !isModifiable.value || isObjectShare.value
            || ((!store.getters.isModerator || isOneToOne.value) && !isMyMsg.value)) {
			return false
		}

		return (moment(message.value.timestamp * 1000).add(1, 'd')) > moment()
	})

}
