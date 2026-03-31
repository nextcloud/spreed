/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	assignConversationToCategoriesParams,
	assignConversationToCategoriesResponse,
	createCategoryParams,
	createCategoryResponse,
	deleteCategoryResponse,
	fetchCategoriesResponse,
	reorderCategoriesParams,
	reorderCategoriesResponse,
	updateCategoryCollapsedParams,
	updateCategoryCollapsedResponse,
	updateCategoryParams,
	updateCategoryResponse,
} from '../types/index.ts'
import type { components } from '../types/openapi/openapi.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export type ConversationCategory = components['schemas']['ConversationCategory']

/**
 * Fetch all conversation categories for the current user
 */
async function fetchCategories(): fetchCategoriesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/categories'))
}

/**
 * Create a new conversation category
 *
 * @param name Name of the category
 */
async function createCategory(name: createCategoryParams['name']): createCategoryResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/categories'), { name })
}

/**
 * Update a conversation category name
 *
 * @param categoryId ID of the category
 * @param name New name for the category
 */
async function updateCategory(categoryId: string, name: updateCategoryParams['name']): updateCategoryResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/categories/{categoryId}', { categoryId }), { name })
}

/**
 * Delete a conversation category
 *
 * @param categoryId ID of the category to delete
 */
async function deleteCategory(categoryId: string): deleteCategoryResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/categories/{categoryId}', { categoryId }))
}

/**
 * Reorder conversation categories
 *
 * @param orderedIds Ordered list of category IDs
 */
async function reorderCategories(orderedIds: reorderCategoriesParams['orderedIds']): reorderCategoriesResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/categories/reorder'), { orderedIds })
}

/**
 * Update the collapsed state of a conversation category
 *
 * @param categoryId ID of the category
 * @param collapsed Whether the category should be collapsed
 */
async function updateCategoryCollapsed(categoryId: string, collapsed: updateCategoryCollapsedParams['collapsed']): updateCategoryCollapsedResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/categories/{categoryId}/collapsed', { categoryId }), { collapsed })
}

/**
 * Assign conversation categories
 *
 * @param token Conversation token
 * @param categoryIds Category IDs to assign (empty array to unassign all)
 */
async function assignConversationToCategories(token: string, categoryIds: assignConversationToCategoriesParams['categoryIds']): assignConversationToCategoriesResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/category', { token }), { categoryIds })
}

export {
	assignConversationToCategories,
	createCategory,
	deleteCategory,
	fetchCategories,
	reorderCategories,
	updateCategory,
	updateCategoryCollapsed,
}
