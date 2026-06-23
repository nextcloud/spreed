/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	assignConversationToTagsParams,
	assignConversationToTagsResponse,
	createTagParams,
	createTagResponse,
	deleteTagResponse,
	fetchTagsResponse,
	reorderTagsParams,
	reorderTagsResponse,
	updateTagCollapsedParams,
	updateTagCollapsedResponse,
	updateTagParams,
	updateTagResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetch all conversation tags for the current user
 */
async function fetchTags(): fetchTagsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/tags'))
}

/**
 * Create a new conversation tag
 *
 * @param name Name of the tag
 */
async function createTag(name: createTagParams['name']): createTagResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/tags'), { name })
}

/**
 * Update a conversation tag name
 *
 * @param tagId ID of the tag
 * @param name New name for the tag
 */
async function updateTag(tagId: string, name: updateTagParams['name']): updateTagResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/tags/{tagId}', { tagId }), { name })
}

/**
 * Delete a conversation tag
 *
 * @param tagId ID of the tag to delete
 */
async function deleteTag(tagId: string): deleteTagResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/tags/{tagId}', { tagId }))
}

/**
 * Reorder conversation tags
 *
 * @param orderedIds Ordered list of tag IDs
 */
async function reorderTags(orderedIds: reorderTagsParams['orderedIds']): reorderTagsResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/tags/reorder'), { orderedIds })
}

/**
 * Update the collapsed state of a conversation tag
 *
 * @param tagId ID of the tag
 * @param collapsed Whether the tag should be collapsed
 */
async function updateTagCollapsed(tagId: string, collapsed: updateTagCollapsedParams['collapsed']): updateTagCollapsedResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/tags/{tagId}/collapsed', { tagId }), { collapsed })
}

/**
 * Assign conversation tags
 *
 * @param token Conversation token
 * @param tagIds Tag IDs to assign (empty array to unassign all)
 */
async function assignConversationToTags(token: string, tagIds: assignConversationToTagsParams['tagIds']): assignConversationToTagsResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/tags', { token }), { tagIds })
}

export {
	assignConversationToTags,
	createTag,
	deleteTag,
	fetchTags,
	reorderTags,
	updateTag,
	updateTagCollapsed,
}
