/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit } from '@nextcloud/event-bus'
import { describe, expect, it, vi } from 'vitest'
import { SettingsAPI, useCustomSettings } from '../SettingsAPI.ts'

vi.mock('@nextcloud/event-bus')

describe('SettingsAPI', () => {
	it('should have open method to open settings', () => {
		expect(SettingsAPI.open).toBeDefined()
		SettingsAPI.open()
		// Currently, a global event is used to open the settings
		expect(emit).toHaveBeenCalledWith('show-settings', undefined)
	})

	it('should have registerSection method to register settings sections', () => {
		const { customSettingsSections } = useCustomSettings()
		expect(customSettingsSections.value).toEqual([])
		expect(SettingsAPI.registerSection).toBeDefined()
		SettingsAPI.registerSection({
			id: 'test',
			name: 'Test',
			element: 'test-element',
		})
		SettingsAPI.registerSection({
			id: 'test2',
			name: 'Test 2',
			element: 'test-element-two',
		})
		expect(customSettingsSections.value).toEqual([{
			id: 'test',
			name: 'Test',
			element: 'test-element',
		}, {
			id: 'test2',
			name: 'Test 2',
			element: 'test-element-two',
		}])
	})

	it('should have unregisterSection method to unregister settings sections', () => {
		const { customSettingsSections } = useCustomSettings()
		expect(customSettingsSections.value).toEqual([{
			id: 'test',
			name: 'Test',
			element: 'test-element',
		}, {
			id: 'test2',
			name: 'Test 2',
			element: 'test-element-two',
		}])
		expect(SettingsAPI.unregisterSection).toBeDefined()
		SettingsAPI.unregisterSection('test')
		expect(customSettingsSections.value).toEqual([{
			id: 'test2',
			name: 'Test 2',
			element: 'test-element-two',
		}])
	})
})
