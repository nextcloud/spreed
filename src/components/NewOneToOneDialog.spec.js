/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NewOneToOneDialog from './NewOneToOneDialog.vue'
import router from '../__mocks__/router.js'
import { ATTENDEE } from '../constants.ts'
import { autocompleteQuery } from '../services/coreService.ts'
import storeConfig from '../store/storeConfig.js'
import { generateOCSResponse } from '../test-helpers.js'

vi.mock('../services/coreService', () => ({
	autocompleteQuery: vi.fn(),
}))

describe('NewOneToOneDialog.vue', () => {
	let store
	let createOneToOneConversationAction

	/**
	 * Mount the dialog and wait for the initial suggestions
	 */
	async function mountComponent() {
		const wrapper = mount(NewOneToOneDialog, {
			global: {
				plugins: [router, store],
				stubs: {
					NcAvatar: true,
					NcDialog: { template: '<div><slot /></div>' },
				},
			},
		})
		await flushPromises()
		return wrapper
	}

	beforeEach(() => {
		setActivePinia(createPinia())

		autocompleteQuery.mockResolvedValue(generateOCSResponse({
			payload: [{ id: 'alice', label: 'Alice', source: ATTENDEE.ACTOR_TYPE.USERS }],
		}))

		const testStoreConfig = cloneDeep(storeConfig)
		createOneToOneConversationAction = vi.fn()
		testStoreConfig.modules.conversationsStore.actions.createOneToOneConversation = createOneToOneConversationAction
		store = createStore(testStoreConfig)
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('suggests local users only', async () => {
		const wrapper = await mountComponent()

		expect(autocompleteQuery).toHaveBeenCalledWith(expect.objectContaining({
			searchText: '',
			onlyUsers: true,
			onlyLocal: true,
		}), expect.anything())
		expect(wrapper.text()).toContain('Alice')
	})

	test('opens the conversation with the clicked user', async () => {
		createOneToOneConversationAction.mockResolvedValue({ token: 'token-alice' })
		const wrapper = await mountComponent()

		await wrapper.find('input[type="checkbox"]').setValue(true)
		await flushPromises()

		expect(createOneToOneConversationAction).toHaveBeenCalledWith(expect.anything(), 'alice')
		expect(router.currentRoute.value.params.token).toBe('token-alice')
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	test('stays open with an error when the conversation cannot be created', async () => {
		createOneToOneConversationAction.mockResolvedValue(undefined)
		const wrapper = await mountComponent()

		await wrapper.find('input[type="checkbox"]').setValue(true)
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('Error while creating the conversation')
		expect(wrapper.emitted('close')).toBeUndefined()
	})
})
