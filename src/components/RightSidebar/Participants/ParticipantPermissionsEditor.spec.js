/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex from 'vuex'
import PermissionsEditor from '../../PermissionsEditor/PermissionsEditor.vue'
import ParticipantPermissionsEditor from './ParticipantPermissionsEditor.vue'
import { ATTENDEE, PARTICIPANT } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useTokenStore } from '../../../stores/token.ts'

describe('ParticipantPermissionsEditor.vue', () => {
	let conversation
	let participant
	let store
	let localVue
	let testStoreConfig

	let tokenStore

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())
		tokenStore = useTokenStore()

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

		tokenStore.token = 'XXTOKENXX'
		const conversationGetterMock = jest.fn().mockReturnValue(conversation)

		testStoreConfig = cloneDeep(storeConfig)
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
	const mountParticipantPermissionsEditor = (participant) => {
		return mount(ParticipantPermissionsEditor, {
			localVue,
			store,
			propsData: {
				participant,
				token: 'fdslk033',
			},
		})
	}

	describe('checkboxes render on mount', () => {
		const testCheckboxRendering = async (participant) => {
			// Arrange
			const permissions = participant.permissions
				|| (PARTICIPANT.PERMISSIONS.MAX_DEFAULT & ~PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) // Default from component

			const permissionsMap = [
				{ ref: 'callStart', value: !!(permissions & PARTICIPANT.PERMISSIONS.CALL_START) },
				{ ref: 'lobbyIgnore', value: !!(permissions & PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) },
				{ ref: 'chatMessagesAndReactions', value: !!(permissions & PARTICIPANT.PERMISSIONS.CHAT) },
				{ ref: 'publishAudio', value: !!(permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) },
				{ ref: 'publishVideo', value: !!(permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO) },
				{ ref: 'publishScreen', value: !!(permissions & PARTICIPANT.PERMISSIONS.PUBLISH_SCREEN) },
			]

			// Act
			const wrapper = await mountParticipantPermissionsEditor(participant)

			// Assert
			for (const permission of permissionsMap) {
				expect(wrapper.findComponent(PermissionsEditor).findComponent({ ref: permission.ref })
					.props('modelValue')).toBe(permission.value)
			}
		}

		it('render checkboxes with custom permissions', async () => {
			await testCheckboxRendering(participant)
		})

		it('render checkboxes with default permissions', async () => {
			participant.permissions = PARTICIPANT.PERMISSIONS.DEFAULT
			await testCheckboxRendering(participant)
		})

		it('render checkboxes with all permissions', async () => {
			participant.permissions = PARTICIPANT.PERMISSIONS.MAX_DEFAULT
			await testCheckboxRendering(participant)
		})

		it('render checkboxes with restricted permissions', async () => {
			participant.permissions = PARTICIPANT.PERMISSIONS.CALL_JOIN
			await testCheckboxRendering(participant)
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
				}),
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
				}),
			)
		})
	})
})
