/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ConversationTag } from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { computed, reactive, watch } from 'vue'
import BrowserStorage from '../services/BrowserStorage.js'
import {
	createTag as createTagApi,
	deleteTag as deleteTagApi,
	fetchTags as fetchTagsApi,
	reorderTags as reorderTagsApi,
	updateTag as updateTagApi,
	updateTagCollapsed as updateTagCollapsedApi,
} from '../services/conversationTagsService.ts'

export const useConversationTagsStore = defineStore('conversationTags', () => {
	const tags = reactive<Record<ConversationTag['id'], ConversationTag>>({})

	// Populate from cache immediately so the UI is snappy on page load
	const conversationTags = BrowserStorage.getItem('conversationTags')
	if (conversationTags) {
		const parsedTags = JSON.parse(conversationTags) as ConversationTag[]
		for (const tag of parsedTags) {
			tags[tag.id] = tag
		}
	}
	// Persist every change to BrowserStorage
	watch(tags, (newTags) => {
		BrowserStorage.setItem('conversationTags', JSON.stringify(Object.values(tags)))
	}, { deep: true })

	/**
	 * Fully replace the current reactive tag map with a new tag list.
	 *
	 * @param newTags Tags to store
	 */
	function replaceTags(newTags: ConversationTag[]) {
		for (const key of Object.keys(tags)) {
			delete tags[key]
		}
		for (const tag of newTags) {
			tags[tag.id] = tag
		}
	}

	const sortedTags = computed(() => Object.values(tags).sort((a, b) => a.sortOrder - b.sortOrder))

	const customTags = computed(() => sortedTags.value.filter((c) => c.type === 'custom'))

	const hasCustomTags = computed(() => customTags.value.length > 0)

	/**
	 * Fetch all conversation tags from the server
	 */
	async function fetchTags() {
		try {
			const response = await fetchTagsApi()
			replaceTags(response.data.ocs.data)
		} catch (error) {
			console.error('Failed to fetch conversation tags:', error)
		}
	}

	/**
	 * Create a new conversation tag
	 *
	 * @param name Name of the tag
	 */
	async function createTag(name: string) {
		const response = await createTagApi(name)
		const tag = response.data.ocs.data
		tags[tag.id] = tag
		return tag
	}

	/**
	 * Update the name of a conversation tag
	 *
	 * @param tagId ID of the tag
	 * @param name New name for the tag
	 */
	async function updateTagName(tagId: string, name: string) {
		try {
			const response = await updateTagApi(tagId, name)
			const tag = response.data.ocs.data
			tags[tag.id] = tag
			return tag
		} catch (error) {
			showError(t('spreed', 'Error renaming tag'))
			throw error
		}
	}

	/**
	 * Remove a conversation tag
	 *
	 * @param tagId ID of the tag to remove
	 */
	async function removeTag(tagId: string) {
		try {
			await deleteTagApi(tagId)
			delete tags[tagId]
		} catch (error) {
			showError(t('spreed', 'Error deleting tag'))
			throw error
		}
	}

	/**
	 * Reorder conversation tags
	 *
	 * @param orderedIds Ordered list of tag IDs
	 */
	async function reorderTags(orderedIds: string[]) {
		try {
			const response = await reorderTagsApi(orderedIds)
			replaceTags(response.data.ocs.data)
		} catch (error) {
			showError(t('spreed', 'Error reordering tags'))
			throw error
		}
	}

	/**
	 * Toggle the collapsed state of a tag (including built-in favorites/other).
	 * Syncs the new state with the server.
	 *
	 * @param tagId DB ID string, or built-in type name ('favorites' | 'other')
	 */
	async function toggleCollapsed(tagId: string) {
		const tag = tags[tagId]!
		const newCollapsed = !tag.collapsed
		// Optimistic update
		tag.collapsed = newCollapsed
		try {
			const response = await updateTagCollapsedApi(tag.id, newCollapsed)
			const updated = response.data.ocs.data
			tags[updated.id] = updated
		} catch (error) {
			// Revert on failure
			tag.collapsed = !newCollapsed
			console.error('Failed to update collapsed state:', error)
		}
	}

	/**
	 * Move a tag by the given offset in the sort order.
	 *
	 * @param tagId ID of the tag to move
	 * @param offset Relative position change
	 */
	async function moveTag(tagId: string, offset: -1 | 1) {
		const orderedIds = sortedTags.value.map((tag) => tag.id)
		const index = orderedIds.indexOf(tagId)
		const nextIndex = index + offset

		if (index === -1 || nextIndex < 0 || nextIndex >= orderedIds.length) {
			return
		}

		const [movedTagId] = orderedIds.splice(index, 1)
		orderedIds.splice(nextIndex, 0, movedTagId)
		await reorderTags(orderedIds)
	}

	return {
		tags,
		sortedTags,
		customTags,
		hasCustomTags,
		fetchTags,
		createTag,
		updateTagName,
		removeTag,
		toggleCollapsed,
		moveTag,
	}
})
