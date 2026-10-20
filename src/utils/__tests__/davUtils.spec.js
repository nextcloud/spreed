/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getBaseUrl } from '@nextcloud/router'
import { describe, expect, it } from 'vitest'
import { generatePublicShareDownloadUrl } from '../davUtils.ts'

describe('davUtils', () => {
	describe('generatePublicShareDownloadUrl', () => {
		it.each([
			['https://nextcloud.ltd/index.php/s/TOKEN', undefined, '/public.php/dav/files/TOKEN'],
			['https://nextcloud.ltd/index.php/s/TOKEN/', undefined, '/public.php/dav/files/TOKEN'],
			['https://nextcloud.ltd/index.php/s/TOKEN', 'photo 1.jpg', '/public.php/dav/files/TOKEN'],
			['https://nextcloud.ltd/index.php/s/TOKEN', '/photo 1.jpg', '/public.php/dav/files/TOKEN/photo%201.jpg'],
			['https://nextcloud.ltd/index.php/s/TOKEN?path=%2FMedia', '/Media/#1.jpg', '/public.php/dav/files/TOKEN/Media/%231.jpg'],
		])('generates download link for %s and %s', (link, path, expected) => {
			expect(generatePublicShareDownloadUrl(link, path)).toBe(getBaseUrl() + expected)
		})
	})
})
