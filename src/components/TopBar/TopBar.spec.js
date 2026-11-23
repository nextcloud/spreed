/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, test } from 'vitest'
import { createStore } from 'vuex'
import TopBar from './TopBar.vue'
import router from '../../__mocks__/router.js'
import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../constants.ts'
import storeConfig from '../../store/storeConfig.js'
import { useParticipantActivityStore } from '../../stores/participantActivity.ts'

const TOKEN = 'XXTOKENXX'

describe('TopBar.vue', () => {
	let store
	let activityStore

	/**
	 * @param {number} attendeeId Unique attendee id
	 * @param {number} inCall Call flag of the participant
	 */
	function addParticipant(attendeeId, inCall = PARTICIPANT.CALL_FLAG.IN_CALL) {
		store.dispatch('addParticipant', {
			token: TOKEN,
			participant: {
				attendeeId,
				displayName: `User ${attendeeId}`,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorId: `actor-${attendeeId}`,
				inCall,
				sessionIds: [`session-${attendeeId}`, `session-${attendeeId}-second`],
			},
		})
	}

	/**
	 * @param {string} sessionId Signaling session id
	 */
	function raiseHand(sessionId) {
		activityStore.setParticipantHandRaised({ sessionId, raisedHand: { state: true, timestamp: Date.now() } })
	}

	/**
	 * @return {Promise<import('@vue/test-utils').VueWrapper>}
	 */
	async function mountTopBar() {
		const wrapper = mount(TopBar, {
			global: {
				plugins: [router, store],
				stubs: {
					BreakoutRoomsEditor: true,
					CalendarEventsDialog: true,
					CallButton: true,
					CallTime: true,
					ConversationIcon: true,
					ExtendOneToOneDialog: true,
					NcPopover: true,
					NcRichText: true,
					TasksCounter: true,
					ThreadHeader: true,
					TopBarMenu: true,
				},
			},
			props: {
				isInCall: true,
			},
		})
		await flushPromises()
		return wrapper
	}

	const findButton = (wrapper) => wrapper.find('.top-bar__participants-button')
	const findBadge = (wrapper) => wrapper.find('.top-bar__raised-hands-badge')

	beforeEach(async () => {
		setActivePinia(createPinia())
		activityStore = useParticipantActivityStore()

		const conversation = {
			token: TOKEN,
			displayName: 'Team call',
			type: CONVERSATION.TYPE.GROUP,
			participantType: PARTICIPANT.TYPE.USER,
			callStartTime: 1700000000,
		}
		const testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => () => conversation
		store = createStore(testStoreConfig)

		// Two participants are in the call, the third one is not
		addParticipant(1)
		addParticipant(2)
		addParticipant(3, PARTICIPANT.CALL_FLAG.DISCONNECTED)

		await router.push({ name: 'conversation', params: { token: TOKEN } })
		await router.isReady()
	})

	test('shows only the participants count without raised hands', async () => {
		const wrapper = await mountTopBar()

		expect(findBadge(wrapper).exists()).toBe(false)
		expect(findButton(wrapper).find('.button-vue__text').text()).toBe('2')
		expect(findButton(wrapper).attributes('aria-label')).toBe('2 participants in call')
	})

	test('shows raised hands next to the participants count', async () => {
		raiseHand('session-1')

		const wrapper = await mountTopBar()

		expect(findBadge(wrapper).text()).toBe('1')
		expect(findButton(wrapper).find('.button-vue__text').text()).toBe('2')
		expect(findButton(wrapper).attributes('aria-label')).toBe('2 participants in call, 1 raised hand')
	})

	test('counts participants, not sessions', async () => {
		raiseHand('session-1')
		raiseHand('session-1-second')
		raiseHand('session-2')
		// The participants list is not loaded in embedded calls
		raiseHand('session-unknown')

		const wrapper = await mountTopBar()

		expect(findBadge(wrapper).text()).toBe('3')
	})
})
