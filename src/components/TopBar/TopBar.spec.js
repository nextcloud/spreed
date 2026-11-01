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
import { useTokenStore } from '../../stores/token.ts'

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
				sessionIds: [`session-${attendeeId}`],
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
		useTokenStore().token = TOKEN

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

	describe('without raised hands', () => {
		test('shows no badge', async () => {
			const wrapper = await mountTopBar()

			expect(findButton(wrapper).exists()).toBe(true)
			expect(findBadge(wrapper).exists()).toBe(false)
		})

		test('shows the number of participants in call', async () => {
			const wrapper = await mountTopBar()

			expect(findButton(wrapper).text()).toBe('2')
		})

		test('describes only the participants in the label', async () => {
			const wrapper = await mountTopBar()

			expect(findButton(wrapper).attributes('aria-label')).toBe('2 participants in call')
		})
	})

	describe('with raised hands', () => {
		beforeEach(() => {
			raiseHand('session-1')
		})

		test('shows the exact number of raised hands in the badge', async () => {
			const wrapper = await mountTopBar()

			expect(findBadge(wrapper).text()).toBe('1')
		})

		test('counts every raised hand', async () => {
			raiseHand('session-2')
			raiseHand('session-3')

			const wrapper = await mountTopBar()

			expect(findBadge(wrapper).text()).toBe('3')
		})

		test('hides the number of participants in call', async () => {
			const wrapper = await mountTopBar()

			// Only the badge is left, the participants count ("2") is not shown
			expect(findButton(wrapper).text()).toBe('1')
		})

		test('adds raised hands to the label', async () => {
			const wrapper = await mountTopBar()

			expect(findButton(wrapper).attributes('aria-label')).toBe('2 participants in call, 1 raised hand')
		})

		test('uses the plural form for several raised hands', async () => {
			raiseHand('session-2')

			const wrapper = await mountTopBar()

			expect(findButton(wrapper).attributes('aria-label')).toBe('2 participants in call, 2 raised hands')
		})
	})
})
