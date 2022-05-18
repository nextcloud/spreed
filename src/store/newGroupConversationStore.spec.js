import Vuex from 'vuex'
import { cloneDeep } from 'lodash'
import { createLocalVue } from '@vue/test-utils'

import newGroupConversationStore from './newGroupConversationStore.js'

describe('newGroupConversationStore', () => {
	let localVue = null
	let store = null

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(cloneDeep(newGroupConversationStore))
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('toggles selected participants', () => {
		store.dispatch('updateSelectedParticipants', { id: 'participant-1' })
		store.dispatch('updateSelectedParticipants', { id: 'participant-2' })
		store.dispatch('updateSelectedParticipants', { id: 'participant-3' })

		expect(store.getters.selectedParticipants).toStrictEqual([
			{ id: 'participant-1' },
			{ id: 'participant-2' },
			{ id: 'participant-3' },
		])

		store.dispatch('updateSelectedParticipants', { id: 'participant-2' })

		expect(store.getters.selectedParticipants).toStrictEqual([
			{ id: 'participant-1' },
			{ id: 'participant-3' },
		])
	})

	test('purges selection', () => {
		expect(store.getters.selectedParticipants).toStrictEqual([])

		store.dispatch('updateSelectedParticipants', { id: 'participant-1' })
		store.dispatch('updateSelectedParticipants', { id: 'participant-2' })

		store.dispatch('purgeNewGroupConversationStore')

		expect(store.getters.selectedParticipants).toStrictEqual([])
	})

})
