/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ConversationCategory } from '../services/conversationCategoriesService.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { computed, reactive } from 'vue'
import BrowserStorage from '../services/BrowserStorage.js'
import {
	createCategory as createCategoryApi,
	deleteCategory as deleteCategoryApi,
	fetchCategories as fetchCategoriesApi,
	reorderCategories as reorderCategoriesApi,
	updateCategory as updateCategoryApi,
	updateCategoryCollapsed as updateCategoryCollapsedApi,
} from '../services/conversationCategoriesService.ts'

export const useConversationCategoriesStore = defineStore('conversationCategories', () => {
	const categories = reactive<Record<string, ConversationCategory>>({})

	// Populate from cache immediately so the UI is snappy on page load
	const cachedCategories = BrowserStorage.getItem('conversationCategories')
	if (cachedCategories) {
		const parsed = JSON.parse(cachedCategories) as ConversationCategory[]
		for (const category of parsed) {
			categories[category.id] = category
		}
	}

	/**
	 *
	 */
	function saveToCache() {
		BrowserStorage.setItem('conversationCategories', JSON.stringify(Object.values(categories)))
	}

	const sortedCategories = computed(() => {
		return Object.values(categories).sort((a, b) => a.sortOrder - b.sortOrder)
	})

	const customCategories = computed(() => {
		return sortedCategories.value.filter((c) => c.type === 'custom')
	})

	const hasCustomCategories = computed(() => {
		return customCategories.value.length > 0
	})

	/**
	 * Get a category by its ID
	 *
	 * @param id Category ID
	 */
	function categoryById(id: number | string): ConversationCategory | undefined {
		return categories[String(id)]
	}

	/**
	 * Get a built-in category by its type ('favorites' or 'other')
	 *
	 * @param type Built-in category type
	 */
	function categoryByType(type: string): ConversationCategory | undefined {
		return Object.values(categories).find((c) => c.type === type)
	}

	/**
	 * Resolve a categoryId that may be a built-in type name ('favorites', 'other') or a numeric DB ID
	 *
	 * @param categoryId
	 */
	function resolveCategory(categoryId: string): ConversationCategory | undefined {
		if (categoryId === 'favorites' || categoryId === 'other') {
			return categoryByType(categoryId)
		}
		return categories[categoryId]
	}

	/**
	 * Fetch all conversation categories from the server
	 */
	async function fetchCategories() {
		try {
			const response = await fetchCategoriesApi()
			const newCategories = response.data.ocs.data
				.reduce((acc: Record<string, ConversationCategory>, category: ConversationCategory) => {
					acc[category.id] = category
					return acc
				}, {})
			for (const key of Object.keys(categories)) {
				delete categories[key]
			}
			Object.assign(categories, newCategories)
			saveToCache()
		} catch (error) {
			console.error('Failed to fetch conversation categories:', error)
		}
	}

	/**
	 * Create a new conversation category
	 *
	 * @param name Name of the category
	 */
	async function createCategory(name: string) {
		try {
			const response = await createCategoryApi(name)
			const category = response.data.ocs.data
			categories[category.id] = category
			saveToCache()
			return category
		} catch (error) {
			console.error('Failed to create category:', error)
			throw error
		}
	}

	/**
	 * Update the name of a conversation category
	 *
	 * @param categoryId ID of the category
	 * @param name New name for the category
	 */
	async function updateCategoryName(categoryId: string, name: string) {
		try {
			const response = await updateCategoryApi(categoryId, name)
			const category = response.data.ocs.data
			categories[category.id] = category
			saveToCache()
			return category
		} catch (error) {
			showError(t('spreed', 'Error renaming category'))
			throw error
		}
	}

	/**
	 * Remove a conversation category
	 *
	 * @param categoryId ID of the category to remove
	 */
	async function removeCategory(categoryId: string) {
		try {
			await deleteCategoryApi(categoryId)
			delete categories[categoryId]
			saveToCache()
		} catch (error) {
			showError(t('spreed', 'Error deleting category'))
			throw error
		}
	}

	/**
	 * Reorder conversation categories
	 *
	 * @param orderedIds Ordered list of category IDs
	 */
	async function reorderCategories(orderedIds: string[]) {
		try {
			const response = await reorderCategoriesApi(orderedIds)
			const data = response.data.ocs.data
			const newCategories: Record<string, ConversationCategory> = {}
			for (const category of data) {
				newCategories[category.id] = category
			}
			for (const key of Object.keys(categories)) {
				delete categories[key]
			}
			Object.assign(categories, newCategories)
			saveToCache()
		} catch (error) {
			showError(t('spreed', 'Error reordering categories'))
			throw error
		}
	}

	/**
	 * Toggle the collapsed state of a category (including built-in favorites/other).
	 * Syncs the new state with the server.
	 *
	 * @param categoryId DB ID string, or built-in type name ('favorites' | 'other')
	 */
	async function toggleCollapsed(categoryId: string) {
		const category = resolveCategory(categoryId)
		if (!category) {
			return
		}
		const newCollapsed = !category.collapsed
		// Optimistic update
		category.collapsed = newCollapsed
		try {
			const response = await updateCategoryCollapsedApi(category.id, newCollapsed)
			const updated = response.data.ocs.data
			categories[updated.id] = updated
			saveToCache()
		} catch (error) {
			// Revert on failure
			category.collapsed = !newCollapsed
			console.error('Failed to update collapsed state:', error)
		}
	}

	/**
	 * Check if a category is collapsed (works for both custom and built-in categories)
	 *
	 * @param categoryId DB ID string, or built-in type name ('favorites' | 'other')
	 */
	function isCollapsed(categoryId: string): boolean {
		return resolveCategory(categoryId)?.collapsed ?? false
	}

	/**
	 * Move a category up in the sort order
	 *
	 * @param categoryId DB ID string, or built-in type name ('favorites' | 'other')
	 */
	async function moveCategoryUp(categoryId: string) {
		const category = resolveCategory(categoryId)
		if (!category) {
			return
		}
		const sorted = sortedCategories.value
		const index = sorted.findIndex((c) => c.id === category.id)
		if (index <= 0) {
			return
		}
		const orderedIds = sorted.map((c) => c.id)
		;[orderedIds[index - 1], orderedIds[index]] = [orderedIds[index], orderedIds[index - 1]]
		await reorderCategories(orderedIds)
	}

	/**
	 * Move a category down in the sort order
	 *
	 * @param categoryId DB ID string, or built-in type name ('favorites' | 'other')
	 */
	async function moveCategoryDown(categoryId: string) {
		const category = resolveCategory(categoryId)
		if (!category) {
			return
		}
		const sorted = sortedCategories.value
		const index = sorted.findIndex((c) => c.id === category.id)
		if (index === -1 || index >= sorted.length - 1) {
			return
		}
		const orderedIds = sorted.map((c) => c.id)
		;[orderedIds[index], orderedIds[index + 1]] = [orderedIds[index + 1], orderedIds[index]]
		await reorderCategories(orderedIds)
	}

	return {
		categories,
		sortedCategories,
		customCategories,
		hasCustomCategories,
		categoryById,
		categoryByType,
		fetchCategories,
		createCategory,
		updateCategoryName,
		removeCategory,
		reorderCategories,
		toggleCollapsed,
		isCollapsed,
		moveCategoryUp,
		moveCategoryDown,
	}
})
