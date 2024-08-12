/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { readonly, shallowReactive } from 'vue'

import { emit } from '@nextcloud/event-bus'

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

const customSettingsSections: TalkSettingsSection[] = shallowReactive([])

/**
 * Register a custom settings section
 * @param section - Settings section
 */
function registerSection(section: TalkSettingsSection) {
	customSettingsSections.push(section)
}

/**
 * Unregister a custom settings section
 * @param id - Section ID
 */
function unregisterSection(id: string) {
	const index = customSettingsSections.findIndex((section) => section.id === id)
	if (index !== -1) {
		customSettingsSections.splice(index, 1)
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
