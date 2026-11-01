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
		addParticipant(3, 'Carol')
		addParticipant(4, 'Dave')
		// Queue order (Carol, Alice) differs from the alphabetical and the insertion order
		raiseHand('session-1', 2000)
		raiseHand('session-3', 1000)

		const wrapper = await mountTab()

		expect(getCaptions(wrapper)).toEqual(['Raised hands', 'Participants'])
		expect(getRaisedHandNames(wrapper)).toEqual(['Carol', 'Alice'])
		expect(getVirtualListNames(wrapper)).toHaveLength(2)
		expect(getVirtualListNames(wrapper)).toEqual(expect.arrayContaining(['Bob', 'Dave']))
	})

	test('ignores a raised hand of a participant who left the call', async () => {
		addParticipant(1, 'Alice')
		addParticipant(2, 'Bob', { inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED })
		raiseHand('session-1', 1000)
		raiseHand('session-2', 500)

		const wrapper = await mountTab()

		expect(getRaisedHandNames(wrapper)).toEqual(['Alice'])
		expect(getVirtualListNames(wrapper)).toEqual(['Bob'])
	})

	test('shows a participant with several raised sessions once', async () => {
		addParticipant(1, 'Alice', { sessionIds: ['session-1a', 'session-1b'] })
		addParticipant(2, 'Bob')
		raiseHand('session-1a', 1000)
		raiseHand('session-1b', 3000)
		raiseHand('session-2', 2000)

		const wrapper = await mountTab()

		// Alice takes the place of her earliest session
		expect(getRaisedHandNames(wrapper)).toEqual(['Alice', 'Bob'])
		expect(getVirtualListNames(wrapper)).toEqual([])
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

	test('shows the "Participants" caption when somebody has no raised hand', async () => {
		addParticipant(1, 'Alice')
		addParticipant(2, 'Bob')
		raiseHand('session-1', 1000)

		const wrapper = await mountTab()

		expect(getCaptions(wrapper)).toEqual(['Raised hands', 'Participants'])
		expect(getVirtualListNames(wrapper)).toEqual(['Bob'])
	})
})
