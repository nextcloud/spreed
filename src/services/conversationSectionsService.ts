/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export type ConversationSection = {
	id: number
	name: string
	sortOrder: number
	collapsed: boolean
}

/**
 * Fetch all conversation sections for the current user
 */
async function fetchSections() {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/sections'))
}

/**
 * Create a new conversation section
 *
 * @param name Name of the section
 */
async function createSection(name: string) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/sections'), { name })
}

/**
 * Update a conversation section name
 *
 * @param sectionId ID of the section
 * @param name New name for the section
 */
async function updateSection(sectionId: number, name: string) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/sections/{sectionId}', { sectionId }), { name })
}

/**
 * Delete a conversation section
 *
 * @param sectionId ID of the section to delete
 */
async function deleteSection(sectionId: number) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/sections/{sectionId}', { sectionId }))
}

/**
 * Reorder conversation sections
 *
 * @param orderedIds Ordered list of section IDs
 */
async function reorderSections(orderedIds: number[]) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/sections/reorder'), { orderedIds })
}

/**
 * Assign a conversation to a section
 *
 * @param token Conversation token
 * @param sectionId Section ID to assign, or null to unassign
 */
async function assignConversationToSection(token: string, sectionId: number | null) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/section', { token }), { sectionId })
}

export {
	assignConversationToSection,
	createSection,
	deleteSection,
	fetchSections,
	reorderSections,
	updateSection,
}
