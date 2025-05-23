/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'

import { emit } from '@nextcloud/event-bus'
import { markRaw, readonly, ref } from 'vue'

type TalkSettingsSection = {
	/**
	 * Section internal ID
	 */
	id: string
	/**
	 * Section visible name
	 */
	name: string
	/**
	 * WebComponent's (custom element's) tag name to render as the section content
	 */
	element: string
}

// TODO: use shallowReactive instead of ref + markRaw in Vue 3 (see file commit history)
const customSettingsSections: Ref<TalkSettingsSection[]> = ref([])

/**
 * Register a custom settings section
 * @param section - Settings section
 */
function registerSection(section: TalkSettingsSection) {
	customSettingsSections.value.push(markRaw(section))
}

/**
 * Unregister a custom settings section
 * @param id - Section ID
 */
function unregisterSection(id: string) {
	const index = customSettingsSections.value.findIndex((section) => section.id === id)
	if (index !== -1) {
		customSettingsSections.value.splice(index, 1)
	}
}

/**
 * Open settings dialog
 */
function open() {
	emit('show-settings', undefined)
}

export const SettingsAPI = {
	open,
	registerSection,
	unregisterSection,
}

/**
 * Composable to use custom settings in Talk
 */
export function useCustomSettings() {
	return {
		customSettingsSections: readonly(customSettingsSections),
	}
}
