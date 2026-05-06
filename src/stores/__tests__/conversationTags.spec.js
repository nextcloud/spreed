/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import BrowserStorage from '../../services/BrowserStorage.js'
import {
	createTag as createTagApi,
	deleteTag as deleteTagApi,
	fetchTags as fetchTagsApi,
	reorderTags as reorderTagsApi,
	updateTag as updateTagApi,
	updateTagCollapsed as updateTagCollapsedApi,
} from '../../services/conversationTagsService.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { useConversationTagsStore } from '../conversationTags.ts'

vi.mock('../../services/BrowserStorage.js', () => ({
	default: {
		getItem: vi.fn().mockReturnValue(null),
		setItem: vi.fn(),
	},
}))

vi.mock('../../services/conversationTagsService.ts', () => ({
	createTag: vi.fn(),
	deleteTag: vi.fn(),
	fetchTags: vi.fn(),
	reorderTags: vi.fn(),
	updateTag: vi.fn(),
	updateTagCollapsed: vi.fn(),
}))

const favoritesTag = {
	id: 'favorites',
	name: 'Favorites',
	type: 'favorites',
	sortOrder: 1,
	collapsed: false,
}

const customTagOne = {
	id: 'tag-1',
	name: 'Alpha',
	type: 'custom',
	sortOrder: 2,
	collapsed: false,
}

const customTagTwo = {
	id: 'tag-2',
	name: 'Beta',
	type: 'custom',
	sortOrder: 3,
	collapsed: true,
}

/**
 * @return {Array<object>}
 */
function getPersistedTags() {
	return JSON.parse(BrowserStorage.setItem.mock.calls.at(-1)[1])
}

describe('conversationTagsStore', () => {
	let conversationTagsStore

	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		vi.restoreAllMocks()
		vi.clearAllMocks()
	})

	it('loads cached tags and exposes sorted custom tags', () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagTwo, favoritesTag, customTagOne]))

		conversationTagsStore = useConversationTagsStore()

		expect(BrowserStorage.getItem).toHaveBeenCalledWith('conversationTags')
		expect(conversationTagsStore.sortedTags.map((tag) => tag.id)).toEqual(['favorites', 'tag-1', 'tag-2'])
		expect(conversationTagsStore.customTags.map((tag) => tag.id)).toEqual(['tag-1', 'tag-2'])
		expect(conversationTagsStore.hasCustomTags).toBe(true)
	})

	it('fetches tags and replaces the stored tag list', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		fetchTagsApi.mockResolvedValue(generateOCSResponse({ payload: [favoritesTag, customTagTwo] }))

		await conversationTagsStore.fetchTags()
		await nextTick()

		expect(fetchTagsApi).toHaveBeenCalled()
		expect(conversationTagsStore.sortedTags.map((tag) => tag.id)).toEqual(['favorites', 'tag-2'])
		expect(conversationTagsStore.tags['tag-1']).toBeUndefined()
		expect(getPersistedTags()).toEqual([favoritesTag, customTagTwo])
	})

	it('logs an error when fetching tags fails', async () => {
		const error = new Error('fetch failed')
		const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
		conversationTagsStore = useConversationTagsStore()
		fetchTagsApi.mockRejectedValue(error)

		await conversationTagsStore.fetchTags()

		expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to fetch conversation tags:', error)
	})

	it('creates a tag and persists it', async () => {
		conversationTagsStore = useConversationTagsStore()
		createTagApi.mockResolvedValue(generateOCSResponse({ payload: customTagOne }))

		const tag = await conversationTagsStore.createTag(customTagOne.name)
		await nextTick()

		expect(createTagApi).toHaveBeenCalledWith(customTagOne.name)
		expect(tag).toEqual(customTagOne)
		expect(conversationTagsStore.tags[customTagOne.id]).toEqual(customTagOne)
		expect(getPersistedTags()).toEqual([customTagOne])
	})

	it('updates a tag name', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		const updatedTag = { ...customTagOne, name: 'Renamed' }
		updateTagApi.mockResolvedValue(generateOCSResponse({ payload: updatedTag }))

		const tag = await conversationTagsStore.updateTagName(customTagOne.id, updatedTag.name)
		await nextTick()

		expect(updateTagApi).toHaveBeenCalledWith(customTagOne.id, updatedTag.name)
		expect(tag).toEqual(updatedTag)
		expect(conversationTagsStore.tags[customTagOne.id]).toEqual(updatedTag)
	})

	it('shows an error when renaming a tag fails', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		const error = new Error('rename failed')
		updateTagApi.mockRejectedValue(error)

		await expect(conversationTagsStore.updateTagName(customTagOne.id, 'Renamed')).rejects.toThrow('rename failed')

		expect(showError).toHaveBeenCalledWith('Error renaming tag')
		expect(conversationTagsStore.tags[customTagOne.id]).toEqual(customTagOne)
	})

	it('removes a tag', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([favoritesTag, customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		deleteTagApi.mockResolvedValue(generateOCSResponse({ payload: [] }))

		await conversationTagsStore.removeTag(customTagOne.id)
		await nextTick()

		expect(deleteTagApi).toHaveBeenCalledWith(customTagOne.id)
		expect(conversationTagsStore.tags[customTagOne.id]).toBeUndefined()
		expect(getPersistedTags()).toEqual([favoritesTag])
	})

	it('shows an error when deleting a tag fails', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		const error = new Error('delete failed')
		deleteTagApi.mockRejectedValue(error)

		await expect(conversationTagsStore.removeTag(customTagOne.id)).rejects.toThrow('delete failed')

		expect(showError).toHaveBeenCalledWith('Error deleting tag')
		expect(conversationTagsStore.tags[customTagOne.id]).toEqual(customTagOne)
	})

	it('moves a tag by reordering with the server response', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagOne, favoritesTag, customTagTwo]))
		conversationTagsStore = useConversationTagsStore()
		const reorderedTags = [
			{ ...favoritesTag, sortOrder: 1 },
			{ ...customTagTwo, sortOrder: 2 },
			{ ...customTagOne, sortOrder: 3 },
		]
		reorderTagsApi.mockResolvedValue(generateOCSResponse({ payload: reorderedTags }))

		await conversationTagsStore.moveTag(customTagOne.id, 1)
		await nextTick()

		expect(reorderTagsApi).toHaveBeenCalledWith(['favorites', 'tag-2', 'tag-1'])
		expect(conversationTagsStore.sortedTags.map((tag) => tag.id)).toEqual(['favorites', 'tag-2', 'tag-1'])
		expect(getPersistedTags()).toEqual(reorderedTags)
	})

	it('shows an error when moving a tag fails during reordering', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([favoritesTag, customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		const error = new Error('reorder failed')
		reorderTagsApi.mockRejectedValue(error)

		await expect(conversationTagsStore.moveTag(customTagOne.id, -1)).rejects.toThrow('reorder failed')

		expect(showError).toHaveBeenCalledWith('Error reordering tags')
		expect(conversationTagsStore.sortedTags.map((tag) => tag.id)).toEqual(['favorites', 'tag-1'])
	})

	it('toggles collapsed state and keeps the server version', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		const collapsedTag = { ...customTagOne, collapsed: true }
		updateTagCollapsedApi.mockResolvedValue(generateOCSResponse({ payload: collapsedTag }))

		const togglePromise = conversationTagsStore.toggleCollapsed(customTagOne.id)

		expect(conversationTagsStore.tags[customTagOne.id].collapsed).toBe(true)

		await togglePromise
		await nextTick()

		expect(updateTagCollapsedApi).toHaveBeenCalledWith(customTagOne.id, true)
		expect(conversationTagsStore.tags[customTagOne.id]).toEqual(collapsedTag)
	})

	it('reverts collapsed state when syncing it fails', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([customTagOne]))
		conversationTagsStore = useConversationTagsStore()
		const error = new Error('collapse failed')
		const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
		updateTagCollapsedApi.mockRejectedValue(error)

		await conversationTagsStore.toggleCollapsed(customTagOne.id)

		expect(updateTagCollapsedApi).toHaveBeenCalledWith(customTagOne.id, true)
		expect(conversationTagsStore.tags[customTagOne.id].collapsed).toBe(false)
		expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to update collapsed state:', error)
	})

	it('ignores invalid moves', async () => {
		BrowserStorage.getItem.mockReturnValueOnce(JSON.stringify([favoritesTag, customTagOne, customTagTwo]))
		conversationTagsStore = useConversationTagsStore()

		await conversationTagsStore.moveTag(favoritesTag.id, -1)
		await conversationTagsStore.moveTag(customTagTwo.id, 1)

		expect(reorderTagsApi).not.toHaveBeenCalled()
	})
})
