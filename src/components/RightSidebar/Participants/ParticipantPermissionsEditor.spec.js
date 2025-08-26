/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import { nextTick } from 'vue'
import { createStore } from 'vuex'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PermissionsEditor from '../../PermissionsEditor/PermissionsEditor.vue'
import ParticipantPermissionsEditor from './ParticipantPermissionsEditor.vue'
import { ATTENDEE, PARTICIPANT } from '../../../constants.ts'
import storeConfig from '../../../store/storeConfig.js'
import { useTokenStore } from '../../../stores/token.ts'

function getByText(wrappers, text) {
	return wrappers.find((wrapper) => wrapper.text().trim() === text)
}

function getPermissionCheckboxes(wrapper) {
	return wrapper.getComponent(PermissionsEditor).findAllComponents(NcCheckboxRadioSwitch)
}

describe('ParticipantPermissionsEditor.vue', () => {
	let conversation
	let participant
	let store
	let testStoreConfig

	let tokenStore

	beforeEach(() => {
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
		const conversationGetterMock = vi.fn().mockReturnValue(conversation)

		testStoreConfig = cloneDeep(storeConfig)
		testStoreConfig.modules.conversationsStore.getters.conversation = () => conversationGetterMock
		// Add a mock function for the action and see if its called and with which arguments
		testStoreConfig.modules.participantsStore.actions.setPermissions = vi.fn()
		store = createStore(testStoreConfig)
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	/**
	 * @param {object} participant Participant with optional user status data
	 */
	const mountParticipantPermissionsEditor = (participant) => {
		return mount(ParticipantPermissionsEditor, {
			global: { plugins: [store] },
			props: {
				participant,
				token: 'fdslk033',
			},
		})
	}

	describe('checkboxes render on mount', () => {
		const testCheckboxRendering = async (participant) => {
			const permissions = participant.permissions
				|| (PARTICIPANT.PERMISSIONS.MAX_DEFAULT & ~PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) // Default from component

			const permissionsMap = [
				{ label: 'Start a call', value: !!(permissions & PARTICIPANT.PERMISSIONS.CALL_START) },
				{ label: 'Skip the lobby', value: !!(permissions & PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) },
				{ label: 'Can post messages and reactions', value: !!(permissions & PARTICIPANT.PERMISSIONS.CHAT) },
				{ label: 'Enable the microphone', value: !!(permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) },
				{ label: 'Enable the camera', value: !!(permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO) },
				{ label: 'Share the screen', value: !!(permissions & PARTICIPANT.PERMISSIONS.PUBLISH_SCREEN) },
			]

			const wrapper = await mountParticipantPermissionsEditor(participant)

			const permissionCheckboxes = getPermissionCheckboxes(wrapper)
			for (const permission of permissionsMap) {
				const checkbox = getByText(permissionCheckboxes, permission.label)
				expect(checkbox.props('modelValue')).toBe(permission.value)
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
			const permissionCheckboxes = getPermissionCheckboxes(wrapper)
			getByText(permissionCheckboxes, 'Skip the lobby').vm.$emit('update:modelValue', true)
			await nextTick()

			// Click the submit button
			await wrapper.findComponent(PermissionsEditor).find('form').trigger('submit')

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
			const permissionCheckboxes = getPermissionCheckboxes(wrapper)
			getByText(permissionCheckboxes, 'Enable the microphone').vm.$emit('update:modelValue', false)
			await nextTick()

			// Click the submit button
			await wrapper.findComponent(PermissionsEditor).find('form').trigger('submit')

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
