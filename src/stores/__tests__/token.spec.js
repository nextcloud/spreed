/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test } from 'vitest'
import BrowserStorage from '../../services/BrowserStorage.js'
import { useTokenStore } from '../token.ts'

describe('tokenStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		BrowserStorage.removeItem('recentTokens')
	})

	test('keeps the last five recently opened tokens, most recent first', () => {
		const tokenStore = useTokenStore()
		for (const token of ['t1', 't2', 't3', 't4', 't5', 't6', 't3']) {
			tokenStore.addRecentToken(token)
		}

		expect(tokenStore.recentTokens).toEqual(['t3', 't6', 't5', 't4', 't2'])
	})

	test('restores recently opened tokens from browser storage', () => {
		useTokenStore().addRecentToken('t1')

		setActivePinia(createPinia())
		expect(useTokenStore().recentTokens).toEqual(['t1'])
	})
})
