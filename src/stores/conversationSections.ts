/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { ConversationSection } from '../services/conversationSectionsService.ts'
import {
	fetchSections as fetchSectionsApi,
	createSection as createSectionApi,
	updateSection as updateSectionApi,
	deleteSection as deleteSectionApi,
	reorderSections as reorderSectionsApi,
} from '../services/conversationSectionsService.ts'
import BrowserStorage from '../services/BrowserStorage.js'

export const useConversationSectionsStore = defineStore('conversationSections', () => {
	const sections = ref<Record<number, ConversationSection>>({})
	const sectionsInitialised = ref(false)

	const sortedSections = computed(() => {
		return Object.values(sections.value).sort((a, b) => a.sortOrder - b.sortOrder)
	})

	function sectionById(id: number): ConversationSection | undefined {
		return sections.value[id]
	}

	async function fetchSections() {
		try {
			const response = await fetchSectionsApi()
			const data = response.data.ocs?.data ?? response.data
			const newSections: Record<number, ConversationSection> = {}
			for (const section of data) {
				// Restore collapsed state from browser storage
				const storedCollapsed = BrowserStorage.getItem(`section_collapsed_${section.id}`)
				if (storedCollapsed !== null) {
					section.collapsed = storedCollapsed === 'true'
				}
				newSections[section.id] = section
			}
			sections.value = newSections
			sectionsInitialised.value = true
		} catch (error) {
			console.error('Failed to fetch conversation sections:', error)
		}
	}

	async function createSection(name: string) {
		try {
			const response = await createSectionApi(name)
			const section = response.data.ocs?.data ?? response.data
			sections.value[section.id] = section
			return section
		} catch (error) {
			console.error('Failed to create section:', error)
			throw error
		}
	}

	async function updateSectionName(sectionId: number, name: string) {
		try {
			const response = await updateSectionApi(sectionId, name)
			const section = response.data.ocs?.data ?? response.data
			sections.value[section.id] = section
			return section
		} catch (error) {
			console.error('Failed to update section:', error)
			throw error
		}
	}

	async function removeSection(sectionId: number) {
		try {
			await deleteSectionApi(sectionId)
			delete sections.value[sectionId]
		} catch (error) {
			console.error('Failed to delete section:', error)
			throw error
		}
	}

	async function reorderSections(orderedIds: number[]) {
		try {
			const response = await reorderSectionsApi(orderedIds)
			const data = response.data.ocs?.data ?? response.data
			const newSections: Record<number, ConversationSection> = {}
			for (const section of data) {
				newSections[section.id] = section
			}
			sections.value = newSections
		} catch (error) {
			console.error('Failed to reorder sections:', error)
			throw error
		}
	}

	function toggleCollapsed(sectionId: number) {
		const section = sections.value[sectionId]
		if (section) {
			const newCollapsed = !section.collapsed
			sections.value = {
				...sections.value,
				[sectionId]: { ...section, collapsed: newCollapsed },
			}
			BrowserStorage.setItem(`section_collapsed_${sectionId}`, String(newCollapsed))
		}
	}

	return {
		sections,
		sectionsInitialised,
		sortedSections,
		sectionById,
		fetchSections,
		createSection,
		updateSectionName,
		removeSection,
		reorderSections,
		toggleCollapsed,
	}
})
