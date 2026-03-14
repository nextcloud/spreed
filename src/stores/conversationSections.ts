/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ConversationSection } from '../services/conversationSectionsService.ts'

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import BrowserStorage from '../services/BrowserStorage.js'
import {
	createSection as createSectionApi,
	deleteSection as deleteSectionApi,
	fetchSections as fetchSectionsApi,
	reorderSections as reorderSectionsApi,
	updateSection as updateSectionApi,
} from '../services/conversationSectionsService.ts'

export const useConversationSectionsStore = defineStore('conversationSections', () => {
	const sections = ref<Record<number, ConversationSection>>({})
	const sectionsInitialised = ref(false)

	const sortedSections = computed(() => {
		return Object.values(sections.value).sort((a, b) => a.sortOrder - b.sortOrder)
	})

	/**
	 * Get a section by its ID
	 *
	 * @param id Section ID
	 */
	function sectionById(id: number): ConversationSection | undefined {
		return sections.value[id]
	}

	/**
	 * Fetch all conversation sections from the server
	 */
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

	/**
	 * Create a new conversation section
	 *
	 * @param name Name of the section
	 */
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

	/**
	 * Update the name of a conversation section
	 *
	 * @param sectionId ID of the section
	 * @param name New name for the section
	 */
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

	/**
	 * Remove a conversation section
	 *
	 * @param sectionId ID of the section to remove
	 */
	async function removeSection(sectionId: number) {
		try {
			await deleteSectionApi(sectionId)
			delete sections.value[sectionId]
		} catch (error) {
			console.error('Failed to delete section:', error)
			throw error
		}
	}

	/**
	 * Reorder conversation sections
	 *
	 * @param orderedIds Ordered list of section IDs
	 */
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

	/**
	 * Toggle the collapsed state of a section
	 *
	 * @param sectionId ID of the section to toggle
	 */
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
