/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export type ConversationCategory = {
	id: number | string
	name: string
	sortOrder: number
	collapsed: boolean
}

/**
 * Fetch all conversation categories for the current user
 */
async function fetchCategories() {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/categories'))
}

/**
 * Create a new conversation category
 *
 * @param name Name of the category
 */
async function createCategory(name: string) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/categories'), { name })
}

/**
 * Update a conversation category name
 *
 * @param categoryId ID of the category
 * @param name New name for the category
 */
async function updateCategory(categoryId: number, name: string) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/categories/{categoryId}', { categoryId }), { name })
}

/**
 * Delete a conversation category
 *
 * @param categoryId ID of the category to delete
 */
async function deleteCategory(categoryId: number) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/categories/{categoryId}', { categoryId }))
}

/**
 * Reorder conversation categories
 *
 * @param orderedIds Ordered list of category IDs
 */
async function reorderCategories(orderedIds: number[]) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/categories/reorder'), { orderedIds })
}

/**
 * Assign a conversation category
 *
 * @param token Conversation token
 * @param categoryId Category ID to assign, or null to unassign
 */
async function assignConversationToCategory(token: string, categoryId: string | null) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/category', { token }), { categoryId })
}

export {
	assignConversationToCategory,
	createCategory,
	deleteCategory,
	fetchCategories,
	reorderCategories,
	updateCategory,
}
