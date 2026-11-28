/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, test } from 'vitest'
import { createStore } from 'vuex'
import NewMessage from './NewMessage.vue'
import router from '../../__mocks__/router.js'
import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../constants.ts'
import storeConfig from '../../store/storeConfig.js'
import { useTokenStore } from '../../stores/token.ts'

const TOKEN = 'XXTOKENXX'

describe('NewMessage.vue', () => {
	let store

	/**
	 * @param {number} attendeeId Unique attendee id
	 * @param {string} actorType Actor type of the participant
	 */
	function addParticipant(attendeeId, actorType = ATTENDEE.ACTOR_TYPE.USERS) {
		store.dispatch('addParticipant', {
			token: TOKEN,
			participant: {
				attendeeId,
				actorType,
				actorId: `actor-${attendeeId}`,
				displayName: `Participant ${attendeeId}`,
			},
		})
	}

	/**
	 * @return {import('@vue/test-utils').VueWrapper}
	 */
	function mountNewMessage() {
		return shallowMount(NewMessage, {
			global: {
				plugins: [router, store],
			},
			props: {
				token: TOKEN,
			},
		})
	}

	beforeEach(() => {
		setActivePinia(createPinia())

		const tokenStore = useTokenStore()
		tokenStore.updateToken(TOKEN)
		tokenStore.updateLastJoinedConversationToken(TOKEN)

		const conversation = {
			token: TOKEN,
			type: CONVERSATION.TYPE.GROUP,
			readOnly: CONVERSATION.STATE.READ_WRITE,
			permissions: PARTICIPANT.PERMISSIONS.CHAT,
			mentionPermissions: CONVERSATION.MENTION_PERMISSIONS.EVERYONE,
			participantType: PARTICIPANT.TYPE.USER,
		}
		const testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => () => conversation
		store = createStore(testStoreConfig)
	})

	test('uses the sidebar participant count for the mention-all warning threshold', async () => {
		for (let attendeeId = 1; attendeeId <= 16; attendeeId++) {
			addParticipant(attendeeId)
		}
		addParticipant(17, ATTENDEE.ACTOR_TYPE.GUESTS)
		addParticipant(18, ATTENDEE.ACTOR_TYPE.EMAILS)
		addParticipant(19, ATTENDEE.ACTOR_TYPE.FEDERATED_USERS)
		addParticipant(101, ATTENDEE.ACTOR_TYPE.GROUPS)
		addParticipant(102, ATTENDEE.ACTOR_TYPE.CIRCLES)
		addParticipant(103, ATTENDEE.ACTOR_TYPE.TEAMS)
		addParticipant(104, ATTENDEE.ACTOR_TYPE.BOTS)
		addParticipant(105, ATTENDEE.ACTOR_TYPE.BRIDGED)

		const wrapper = mountNewMessage()
		await wrapper.setData({ text: '@all hello' })

		expect(wrapper.vm.participantCount).toBe(19)
		expect(wrapper.vm.showMentionAllWarning).toBe(false)

		addParticipant(20)

		expect(wrapper.vm.participantCount).toBe(20)
		expect(wrapper.vm.showMentionAllWarning).toBe(true)
		expect(wrapper.vm.mentionAllWarningText).toBe('This will notify 20 participants.')
	})
})
