/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, test } from 'vitest'
import {
	getBrowseSessionRequestConfig,
	getBrowseSessionTabId,
	resetBrowseSessionTabId,
} from './talkSessionUniqueTabId.ts'

const HEADER = 'x-nextcloud-talk-session-tab-id'

describe('talkSessionUniqueTabId - browse session', () => {
	afterEach(() => {
		resetBrowseSessionTabId()
	})

	test('generates a stable browse tab id across calls', () => {
		const first = getBrowseSessionTabId()
		const second = getBrowseSessionTabId()
		expect(first).toBe(second)
		expect(first).toHaveLength(64)
	})

	test('rotates the browse tab id on reset', () => {
		const before = getBrowseSessionTabId()
		resetBrowseSessionTabId()
		const after = getBrowseSessionTabId()
		expect(after).not.toBe(before)
	})

	test('request config carries the browse tab id header', () => {
		const config = getBrowseSessionRequestConfig()
		expect((config.headers as Record<string, string>)[HEADER]).toBe(getBrowseSessionTabId())
	})

	test('request config merges provided config and headers', () => {
		const config = getBrowseSessionRequestConfig({
			params: { token: 'abc' },
			headers: { 'X-Custom': '1' },
		})
		expect(config.params).toEqual({ token: 'abc' })
		expect((config.headers as Record<string, string>)['X-Custom']).toBe('1')
		expect((config.headers as Record<string, string>)[HEADER]).toBe(getBrowseSessionTabId())
	})
})
