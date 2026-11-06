/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { getEmojiTitle } from '../emoji.ts'

describe('emoji', () => {
	describe('getEmojiTitle', () => {
		it('returns the short name wrapped in colons', () => {
			expect(getEmojiTitle('😅')).toBe(':sweat_smile:')
		})

		it('returns undefined for unknown input', () => {
			expect(getEmojiTitle('x')).toBeUndefined()
		})
	})
})
