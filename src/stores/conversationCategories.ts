/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ConversationCategory } from '../services/conversationCategoriesService.ts'

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import BrowserStorage from '../services/BrowserStorage.js'
import {
	createCategory as createCategoryApi,
	deleteCategory as deleteCategoryApi,
	fetchCategories as fetchCategoriesApi,
	reorderCategories as reorderCategoriesApi,
	updateCategory as updateCategoryApi,
} from '../services/conversationCategoriesService.ts'

export const useConversationCategoriesStore = defineStore('conversationCategories', () => {
	const categories = ref<Record<string, ConversationCategory>>({})
	const categoriesInitialised = ref(false)

	const sortedCategories = computed(() => {
		return Object.values(categories.value).sort((a, b) => a.sortOrder - b.sortOrder)
	})

	/**
	 * Get a category by its ID
	 *
	 * @param id Category ID
	 */
	function categoryById(id: number | string): ConversationCategory | undefined {
		return categories.value[String(id)]
	}

	/**
	 * Fetch all conversation categories from the server
	 */
	async function fetchCategories() {
		try {
			const response = await fetchCategoriesApi()
			const data = response.data.ocs?.data ?? response.data
			const newCategories: Record<string, ConversationCategory> = {}
			for (const category of data) {
				// Restore collapsed state from browser storage
				const storedCollapsed = BrowserStorage.getItem(`category_collapsed_${category.id}`)
				if (storedCollapsed !== null) {
					category.collapsed = storedCollapsed === 'true'
				}
				newCategories[category.id] = category
			}
			categories.value = newCategories
			categoriesInitialised.value = true
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
			const category = response.data.ocs?.data ?? response.data
			categories.value[category.id] = category
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
			const category = response.data.ocs?.data ?? response.data
			categories.value[category.id] = category
			return category
		} catch (error) {
			console.error('Failed to update category:', error)
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
			delete categories.value[categoryId]
		} catch (error) {
			console.error('Failed to delete category:', error)
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
			const data = response.data.ocs?.data ?? response.data
			const newCategories: Record<string, ConversationCategory> = {}
			for (const category of data) {
				newCategories[category.id] = category
			}
			categories.value = newCategories
		} catch (error) {
			console.error('Failed to reorder categories:', error)
			throw error
		}
	}

	/**
	 * Toggle the collapsed state of a category
	 *
	 * @param categoryId ID of the category to toggle
	 */
	function toggleCollapsed(categoryId: number | string) {
		const category = categories.value[String(categoryId)]
		if (category) {
			const newCollapsed = !category.collapsed
			categories.value = {
				...categories.value,
				[categoryId]: { ...category, collapsed: newCollapsed },
			}
			BrowserStorage.setItem(`category_collapsed_${categoryId}`, String(newCollapsed))
		}
	}

	return {
		categories,
		categoriesInitialised,
		sortedCategories,
		categoryById,
		fetchCategories,
		createCategory,
		updateCategoryName,
		removeCategory,
		reorderCategories,
		toggleCollapsed,
	}
})
