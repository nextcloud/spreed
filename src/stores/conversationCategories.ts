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
} from '../services/conversationCategoriesService.ts'

type CategoryWithCollapsed = ConversationCategory & { collapsed: boolean }

const STORAGE_KEY = 'conversation-categories'

/**
 * Read cached categories from BrowserStorage
 */
function readCachedCategories(): Record<string, CategoryWithCollapsed> {
	return JSON.parse(BrowserStorage.getItem(STORAGE_KEY) ?? '{}') as Record<string, CategoryWithCollapsed>
}

/**
 * Persist categories to BrowserStorage
 *
 * @param categories Categories record to persist
 */
function persistCategories(categories: Record<string, CategoryWithCollapsed>) {
	BrowserStorage.setItem(STORAGE_KEY, JSON.stringify(categories))
}

export const useConversationCategoriesStore = defineStore('conversationCategories', () => {
	const categories = reactive<Record<string, CategoryWithCollapsed>>(readCachedCategories())

	const sortedCategories = computed(() => {
		return Object.values(categories).sort((a, b) => a.sortOrder - b.sortOrder)
	})

	/**
	 * Get a category by its ID
	 *
	 * @param id Category ID
	 */
	function categoryById(id: number | string): CategoryWithCollapsed | undefined {
		return categories[String(id)]
	}

	/**
	 * Fetch all conversation categories from the server
	 */
	async function fetchCategories() {
		try {
			const response = await fetchCategoriesApi()
			const storedCategories = readCachedCategories()
			const newCategories = response.data.ocs.data
				.reduce((acc: Record<string, CategoryWithCollapsed>, category: ConversationCategory) => {
					acc[category.id] = { ...category, collapsed: storedCategories[category.id]?.collapsed ?? false }
					return acc
				}, {})
			// Clear and repopulate the reactive object
			for (const key of Object.keys(categories)) {
				delete categories[key]
			}
			Object.assign(categories, newCategories)
			persistCategories(categories)
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
			categories[category.id] = { ...category, collapsed: false }
			persistCategories(categories)
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
	async function updateCategoryName(categoryId: number, name: string) {
		try {
			const response = await updateCategoryApi(categoryId, name)
			const category = response.data.ocs.data
			const collapsed = categories[category.id]?.collapsed ?? false
			categories[category.id] = { ...category, collapsed }
			persistCategories(categories)
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
	async function removeCategory(categoryId: number) {
		try {
			await deleteCategoryApi(categoryId)
			delete categories[categoryId]
			persistCategories(categories)
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
	async function reorderCategories(orderedIds: number[]) {
		try {
			const response = await reorderCategoriesApi(orderedIds)
			const data = response.data.ocs.data
			const newCategories: Record<string, CategoryWithCollapsed> = {}
			for (const category of data) {
				const collapsed = categories[category.id]?.collapsed ?? false
				newCategories[category.id] = { ...category, collapsed }
			}
			for (const key of Object.keys(categories)) {
				delete categories[key]
			}
			Object.assign(categories, newCategories)
			persistCategories(categories)
		} catch (error) {
			showError(t('spreed', 'Error reordering categories'))
			throw error
		}
	}

	/**
	 * Toggle the collapsed state of a category (including built-in favorites/other)
	 *
	 * @param categoryId ID of the category to toggle
	 */
	function toggleCollapsed(categoryId: number | string) {
		const key = String(categoryId)
		const category = categories[key]
		if (category) {
			category.collapsed = !category.collapsed
		} else {
			// Built-in categories (favorites, other) - create a synthetic entry
			categories[key] = { id: key as unknown as number, name: key, sortOrder: 0, collapsed: true }
		}
		persistCategories(categories)
	}

	/**
	 * Check if a category is collapsed (works for both custom and built-in categories)
	 *
	 * @param categoryId ID of the category to check
	 */
	function isCollapsed(categoryId: number | string): boolean {
		return categories[String(categoryId)]?.collapsed ?? false
	}

	/**
	 * Move a category up in the sort order
	 *
	 * @param categoryId ID of the category to move
	 */
	async function moveCategoryUp(categoryId: number) {
		const sorted = sortedCategories.value
		const index = sorted.findIndex((c) => c.id === categoryId)
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
	 * @param categoryId ID of the category to move
	 */
	async function moveCategoryDown(categoryId: number) {
		const sorted = sortedCategories.value
		const index = sorted.findIndex((c) => c.id === categoryId)
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
		categoryById,
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
