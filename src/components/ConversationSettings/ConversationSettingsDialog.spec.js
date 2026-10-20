/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createStore } from 'vuex'
import ConversationSettingsDialog from './ConversationSettingsDialog.vue'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'

vi.mock('@nextcloud/vue/components/NcAppSettingsDialog', () => ({
	default: { name: 'NcAppSettingsDialog', template: '<div><slot /></div>' },
}))

vi.mock('@nextcloud/vue/components/NcAppSettingsSection', () => ({
	default: { name: 'NcAppSettingsSection', template: '<div><slot /></div>' },
}))

vi.mock('../../services/CapabilitiesManager.ts', async (importOriginal) => ({
	...(await importOriginal()),
	getTalkConfig: vi.fn(() => true),
	hasTalkFeature: vi.fn(() => true),
	showTalkFeatureHint: vi.fn(() => false),
}))

describe('ConversationSettingsDialog.vue', () => {
	const TOKEN = 'XXTOKENXX'

	/**
	 * @param {string} objectType object type of the conversation
	 */
	async function mountDialog(objectType) {
		const store = createStore({
			getters: {
				conversation: () => () => ({
					token: TOKEN,
					type: CONVERSATION.TYPE.GROUP,
					objectType,
					participantType: PARTICIPANT.TYPE.OWNER,
					attributes: 0,
					canDeleteConversation: false,
					canLeaveConversation: true,
				}),
			},
		})

		const wrapper = shallowMount(ConversationSettingsDialog, {
			global: {
				plugins: [store],
				renderStubDefaultSlot: true,
			},
		})
		wrapper.vm.token = TOKEN
		await wrapper.vm.$nextTick()

		return [
			...wrapper.findAllComponents({ name: 'NcAppSettingsSection' }).map((section) => section.attributes('id')),
			...HIDDEN_COMPONENTS.filter((name) => wrapper.findComponent({ name }).exists()),
		]
	}

	const HIDDEN_SECTIONS = ['meeting', 'permissions', 'live-transcription', 'breakout-rooms', 'matterbridge', 'bots']
	const HIDDEN_COMPONENTS = ['LinkShareSettings', 'ExpirationSettings', 'BanSettings', 'LockingSettings']

	it('shows moderation settings in regular conversations', async () => {
		const settings = await mountDialog(CONVERSATION.OBJECT_TYPE.DEFAULT)
		expect(settings).toEqual(expect.arrayContaining([...HIDDEN_SECTIONS, ...HIDDEN_COMPONENTS]))
	})

	it('hides unsupported moderation settings in Matrix conversations', async () => {
		const settings = await mountDialog(CONVERSATION.OBJECT_TYPE.MATRIX)
		expect(settings).toContain('conversation-settings')
		expect(settings).toContain('dangerzone')
		for (const hidden of [...HIDDEN_SECTIONS, ...HIDDEN_COMPONENTS]) {
			expect(settings).not.toContain(hidden)
		}
	})
})
