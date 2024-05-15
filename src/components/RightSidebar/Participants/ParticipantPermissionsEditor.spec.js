/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import ParticipantPermissionsEditor from './ParticipantPermissionsEditor.vue'
import PermissionsEditor from '../../PermissionsEditor/PermissionsEditor.vue'

import { PARTICIPANT, ATTENDEE } from '../../../constants.js'
import storeConfig from '../../../store/storeConfig.js'

describe('ParticipantPermissionsEditor.vue', () => {
	let conversation
	let participant
	let store
	let testStoreConfig

	beforeEach(() => {
		participant = {
			displayName: 'Alice',
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			actorId: 'alice-actor-id',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participantType: PARTICIPANT.TYPE.USER,
			permissions: PARTICIPANT.PERMISSIONS.CALL_START
				| PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
				| PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
				| PARTICIPANT.PERMISSIONS.CUSTOM,
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
		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(testStoreConfig)

	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	/**
	 * @param {object} participant Participant with optional user status data
	 */
	const mountParticipantPermissionsEditor = (participant) => {
		return mount(ParticipantPermissionsEditor, {
			global: {
				plugins: [store],
			},
			props: {
				participant,
				token: 'fdslk033',
			},
		})
	}

	describe('Properly renders the checkboxes when mounted', () => {
		test('Properly renders the call start checkbox', async () => {
			const wrapper = await mountParticipantPermissionsEditor(participant)
			const callStartCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'callStart' })
			expect(callStartCheckbox.props('modelValue')).toBe(true)
		})

		test('Properly renders the lobby Ignore checkbox', async () => {
			const wrapper = await mountParticipantPermissionsEditor(participant)
			const lobbyIgnoreCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'lobbyIgnore' })
			expect(lobbyIgnoreCheckbox.props('modelValue')).toBe(false)
		})

		test('Properly renders the publish audio checkbox', async () => {
			const wrapper = await mountParticipantPermissionsEditor(participant)
			const publishAudioCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'publishAudio' })
			expect(publishAudioCheckbox.props('modelValue')).toBe(true)
		})

		test('Properly renders the publish video checkbox', async () => {
			const wrapper = await mountParticipantPermissionsEditor(participant)
			const publishVideoCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'publishVideo' })
			expect(publishVideoCheckbox.props('modelValue')).toBe(true)
		})

		test('Properly renders the publish screen checkbox', async () => {
			const wrapper = await mountParticipantPermissionsEditor(participant)
			const publishScreenCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'publishScreen' })
			expect(publishScreenCheckbox.props('modelValue')).toBe(false)
		})

		test('Properly renders the checkboxes with default permissions', async () => {
			participant.permissions = PARTICIPANT.PERMISSIONS.DEFAULT
			const wrapper = await mountParticipantPermissionsEditor(participant)
			const callStartCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'callStart' })
			expect(callStartCheckbox.props('modelValue')).toBe(true)
			const lobbyIgnoreCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'lobbyIgnore' })
			expect(lobbyIgnoreCheckbox.props('modelValue')).toBe(false)
			const publishAudioCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'publishAudio' })
			expect(publishAudioCheckbox.props('modelValue')).toBe(true)
			const publishVideoCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'publishVideo' })
			expect(publishVideoCheckbox.props('modelValue')).toBe(true)
			const publishScreenCheckbox = wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'publishScreen' })
			expect(publishScreenCheckbox.props('modelValue')).toBe(true)
		})
	})

	describe('Dispatches the action to set the right permissions', () => {

		test('Dispatches setPermissions with the correct permissions value when a permission is added', async () => {
			const wrapper = await mountParticipantPermissionsEditor(participant)

			// Add a permission
			await wrapper.findComponent(PermissionsEditor).setData({ lobbyIgnore: true })

			// Click the submit button
			await wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'submit' }).trigger('click')

			expect(testStoreConfig.modules.participantsStore.actions.setPermissions).toHaveBeenCalledWith(
				// The first argument is the context object
				expect.anything(),
				expect.objectContaining({
					permissions: PARTICIPANT.PERMISSIONS.CALL_START
						| PARTICIPANT.PERMISSIONS.CALL_JOIN
						| PARTICIPANT.PERMISSIONS.LOBBY_IGNORE
						| PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
						| PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
						| PARTICIPANT.PERMISSIONS.CUSTOM,
				})
			)
		})

		test('Dispatches setPermissions with the correct permissions value when a permission is substracted', async () => {
			const wrapper = mountParticipantPermissionsEditor(participant)

			// Remove a permission
			await wrapper.findComponent(PermissionsEditor).setData({ publishAudio: false })

			// Click the submit button
			await wrapper.findComponent(PermissionsEditor).findComponent({ ref: 'submit' }).trigger('click')

			expect(testStoreConfig.modules.participantsStore.actions.setPermissions).toHaveBeenCalledWith(
				// The first argument is the context object
				expect.anything(),
				expect.objectContaining({
					permissions: PARTICIPANT.PERMISSIONS.CALL_START
						| PARTICIPANT.PERMISSIONS.CALL_JOIN
						| PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
						| PARTICIPANT.PERMISSIONS.CUSTOM,
				})
			)
		})
	})
})
