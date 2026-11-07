/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import ParticipantsTab from './ParticipantsTab.vue'
import router from '../../../__mocks__/router.js'
import { ATTENDEE, PARTICIPANT } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useParticipantActivityStore } from '../../../stores/participantActivity.ts'

// Participants are fetched by a composable, which is not relevant here
vi.mock('../../../composables/useGetParticipants.ts', () => ({
	useGetParticipants: () => ({ cancelableGetParticipants: vi.fn() }),
}))

const TOKEN = 'XXTOKENXX'

// Stubs expose the props of the real lists as visible text
const ParticipantsListStub = {
	name: 'ParticipantsList',
	props: ['items'],
	template: '<ul><li v-for="item in items" :key="item.attendeeId">{{ item.displayName }}</li></ul>',
}
const ParticipantsListVirtualStub = {
	name: 'ParticipantsListVirtual',
	props: ['participants', 'loading'],
	template: '<ul><li v-for="item in participants" :key="item.attendeeId">{{ item.displayName }}</li></ul>',
}

describe('ParticipantsTab.vue', () => {
	let store
	let activityStore

	/**
	 * @param {number} attendeeId Unique attendee id
	 * @param {string} displayName Name shown in the list
	 * @param {object} [data] Overrides for the participant
	 */
	function addParticipant(attendeeId, displayName, data = {}) {
		store.dispatch('addParticipant', {
			token: TOKEN,
			participant: {
				attendeeId,
				displayName,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorId: `actor-${attendeeId}`,
				participantType: PARTICIPANT.TYPE.USER,
				inCall: PARTICIPANT.CALL_FLAG.IN_CALL,
				sessionIds: [`session-${attendeeId}`],
				...data,
			},
		})
	}

	/**
	 * @param {string} sessionId Signaling session id
	 * @param {number} timestamp Time when the hand was raised
	 */
	function raiseHand(sessionId, timestamp) {
		activityStore.setParticipantHandRaised({ sessionId, raisedHand: { state: true, timestamp } })
	}

	/**
	 * @return {Promise<import('@vue/test-utils').VueWrapper>}
	 */
	async function mountTab() {
		const wrapper = mount(ParticipantsTab, {
			global: {
				plugins: [router, store],
				stubs: {
					ParticipantsList: ParticipantsListStub,
					ParticipantsListVirtual: ParticipantsListVirtualStub,
					ParticipantsSearchResults: true,
					SearchBox: true,
					DialpadPanel: true,
					SelectPhoneNumber: true,
				},
			},
			props: {
				canSearch: false,
				canAdd: false,
			},
		})
		await flushPromises()
		return wrapper
	}

	const getCaptions = (wrapper) => wrapper.findAllComponents(NcAppNavigationCaption).map((caption) => caption.props('name'))
	const getRaisedHandNames = (wrapper) => wrapper.find('.raised-hands').findAll('li').map((item) => item.text())
	const getVirtualListNames = (wrapper) => wrapper.findComponent(ParticipantsListVirtualStub).props('participants').map((participant) => participant.displayName)

	beforeEach(async () => {
		setActivePinia(createPinia())
		activityStore = useParticipantActivityStore()

		store = createStore(cloneDeep(storeConfig))
		await router.push({ name: 'conversation', params: { token: TOKEN } })
		await router.isReady()
	})

	test('shows only the regular list when nobody raised a hand', async () => {
		addParticipant(1, 'Alice')
		addParticipant(2, 'Bob')

		const wrapper = await mountTab()

		expect(getCaptions(wrapper)).not.toContain('Raised hands')
		expect(getCaptions(wrapper)).not.toContain('Participants')
		expect(wrapper.find('.raised-hands').exists()).toBe(false)
		expect(getVirtualListNames(wrapper)).toHaveLength(2)
		expect(getVirtualListNames(wrapper)).toEqual(expect.arrayContaining(['Alice', 'Bob']))
	})

	test('lists raised hands in queue order and removes them from the regular list', async () => {
		addParticipant(1, 'Alice')
		addParticipant(2, 'Bob')
		addParticipant(3, 'Carol', { sessionIds: ['session-3a', 'session-3b'] })
		addParticipant(4, 'Dave')
		addParticipant(5, 'Eve', { inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED })
		// Queue order (Carol, Alice) differs from the alphabetical and the insertion order.
		// Carol is shown once, at the place of her earliest session.
		raiseHand('session-1', 2000)
		raiseHand('session-3a', 1000)
		raiseHand('session-3b', 3000)
		// Eve left the call, so her hand is ignored
		raiseHand('session-5', 500)

		const wrapper = await mountTab()

		expect(getCaptions(wrapper)).toEqual(['Raised hands', 'Participants'])
		expect(getRaisedHandNames(wrapper)).toEqual(['Carol', 'Alice'])
		expect(getVirtualListNames(wrapper)).toHaveLength(3)
		expect(getVirtualListNames(wrapper)).toEqual(expect.arrayContaining(['Bob', 'Dave', 'Eve']))
	})

	test('hides the "Participants" caption when everybody raised a hand', async () => {
		addParticipant(1, 'Alice')
		addParticipant(2, 'Bob')
		raiseHand('session-1', 1000)
		raiseHand('session-2', 2000)

		const wrapper = await mountTab()

		expect(getCaptions(wrapper)).toEqual(['Raised hands'])
		expect(getVirtualListNames(wrapper)).toEqual([])
	})
})
