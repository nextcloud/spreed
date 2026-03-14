/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export type ConversationSection = {
	id: number,
	name: string,
	sortOrder: number,
	collapsed: boolean,
}

async function fetchSections() {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/sections'))
}

async function createSection(name: string) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/sections'), { name })
}

async function updateSection(sectionId: number, name: string) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/sections/{sectionId}', { sectionId }), { name })
}

async function deleteSection(sectionId: number) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/sections/{sectionId}', { sectionId }))
}

async function reorderSections(orderedIds: number[]) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/sections/reorder'), { orderedIds })
}

async function assignConversationToSection(token: string, sectionId: number | null) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/section', { token }), { sectionId })
}

export {
	fetchSections,
	createSection,
	updateSection,
	deleteSection,
	reorderSections,
	assignConversationToSection,
}
