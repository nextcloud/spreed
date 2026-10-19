/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl, getBaseUrl } from '@nextcloud/router'
import { describe, expect, it } from 'vitest'
import { generatePreviewUrl, generatePublicShareDownloadUrl, generatePublicSharePreviewUrl } from '../davUtils.ts'

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

	describe('generatePreviewUrl', () => {
		it.each([
			[123, { x: 32, y: 32 }, '/core/preview?fileId=123&x=32&y=32&a=1'],
			['123', { x: -1, y: 384 }, '/core/preview?fileId=123&x=-1&y=384&a=1'],
		])('generates preview link for %s and %o', (fileId, size, expected) => {
			expect(generatePreviewUrl(fileId, size)).toBe(generateUrl(expected))
		})
	})

	describe('generatePublicSharePreviewUrl', () => {
		it.each([
			['https://nextcloud.ltd/index.php/s/TOKEN', 'photo 1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=&x=-1&y=384&a=1'],
			['https://nextcloud.ltd/index.php/s/TOKEN/', 'photo 1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=&x=-1&y=384&a=1'],
			['https://nextcloud.ltd/index.php/s/TOKEN', '/photo 1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=%2Fphoto%201.jpg&x=-1&y=384&a=1'],
			['https://nextcloud.ltd/index.php/s/TOKEN?path=%2FMedia', '/Media/#1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=%2FMedia%2F%231.jpg&x=-1&y=384&a=1'],
		])('generates preview link for %s and %s', (link, path, expected) => {
			expect(generatePublicSharePreviewUrl(link, path, { x: -1, y: 384 })).toBe(generateUrl(expected))
		})
	})
})
