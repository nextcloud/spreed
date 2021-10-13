import Vuex from 'vuex'
import { createLocalVue, mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import storeConfig from '../../../../../../store/storeConfig'
import { PARTICIPANT, ATTENDEE } from '../../../../../../constants'

import ParticipantPermissionsEditor from './ParticipantPermissionsEditor'

describe('ParticipantPermissionsEditor.vue', () => {
	let conversation
	let participant
	let store
	let localVue
	let testStoreConfig

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		participant = {
			displayName: 'Alice',
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			actorId: 'alice-actor-id',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participantType: PARTICIPANT.TYPE.USER,
			permissions: PARTICIPANT.PERMISSIONS.CALL_START
				| PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
				| PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO,
			attendeeId: 'alice-attendee-id',
			status: '',
			statusIcon: '🌧️',
			statusMessage: 'rainy',
			sessionIds: [
				'session-id-alice',
			],
		}

		const conversationGetterMock = jest.fn().mockReturnValue(conversation)

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.tokenStore.getters.getToken = () => () => 'current-token'
		testStoreConfig.modules.conversationsStore.getters.conversation = () => conversationGetterMock
		// Add a mock function for the action and see if its called and with which arguments
		testStoreConfig.modules.participantsStore.actions.setPermissions = jest.fn()
		store = new Vuex.Store(testStoreConfig)

	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	/**
	 * @param {object} participant Participant with optional user status data
	 */
	function mountParticipantPermissionsEditor(participant) {

		return mount(ParticipantPermissionsEditor, {
			localVue,
			store,
			propsData: {
				participant,
				token: 'fdslk033',
			},
		})
	}

	describe('Properly renders the checkboxes when mounted', () => {
		test('Properly renders the call start checkbox', () => {
			const wrapper = mountParticipantPermissionsEditor(participant)
			const callStartCheckbox = wrapper.findComponent({ ref: 'callStart' })
			expect(callStartCheckbox.vm.$options.propsData.checked).toBe(true)
		})

		test('Properly renders the lobby Ignore checkbox', () => {
			const wrapper = mountParticipantPermissionsEditor(participant)
			const lobbyIgnoreCheckbox = wrapper.findComponent({ ref: 'lobbyIgnore' })
			expect(lobbyIgnoreCheckbox.vm.$options.propsData.checked).toBe(false)
		})

		test('Properly renders the publish audio checkbox', () => {
			const wrapper = mountParticipantPermissionsEditor(participant)
			const publishAudioCheckbox = wrapper.findComponent({ ref: 'publishAudio' })
			expect(publishAudioCheckbox.vm.$options.propsData.checked).toBe(true)
		})

		test('Properly renders the publish video checkbox', () => {
			const wrapper = mountParticipantPermissionsEditor(participant)
			const publishVideoCheckbox = wrapper.findComponent({ ref: 'publishVideo' })
			expect(publishVideoCheckbox.vm.$options.propsData.checked).toBe(true)
		})

		test('Properly renders the publish screen checkbox', () => {
			const wrapper = mountParticipantPermissionsEditor(participant)
			const publishScreenCheckbox = wrapper.findComponent({ ref: 'publishScreen' })
			expect(publishScreenCheckbox.vm.$options.propsData.checked).toBe(false)
		})
	})

	describe('Dispatches the aciton to set the right permissions', () => {

		test('Dispatches setPermissions with the correct permissions value when a permission is subtracted', async () => {
			const wrapper = mountParticipantPermissionsEditor(participant)

			// Add a permission
			wrapper.setData({ lobbyIgnore: true })

			// Click the submit button
			await wrapper.find({ ref: 'submit' }).trigger('click')

			expect(testStoreConfig.modules.participantsStore.actions.setPermissions).toHaveBeenCalledWith(
				// The first argument is the context object
				expect.anything(),
				expect.objectContaining({
					permissions: PARTICIPANT.PERMISSIONS.CALL_START
						| PARTICIPANT.PERMISSIONS.LOBBY_IGNORE
						| PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
						| PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
						| PARTICIPANT.PERMISSIONS.CUSTOM,
				})
			)
		})

		test('Dispatches setPermissions with the correct permissions value when a permission is added', async () => {
			const wrapper = mountParticipantPermissionsEditor(participant)

			// Remove a permission
			wrapper.setData({ publishAudio: false })

			// Click the submit button
			await wrapper.find({ ref: 'submit' }).trigger('click')

			expect(testStoreConfig.modules.participantsStore.actions.setPermissions).toHaveBeenCalledWith(
				// The first argument is the context object
				expect.anything(),
				expect.objectContaining({
					permissions: PARTICIPANT.PERMISSIONS.CALL_START
						| PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
						| PARTICIPANT.PERMISSIONS.CUSTOM,
				})
			)
		})
	})
})
