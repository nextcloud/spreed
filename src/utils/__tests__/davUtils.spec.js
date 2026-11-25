/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, getBaseUrl } from '@nextcloud/router'
import { describe, expect, it, vi } from 'vitest'
import { generatePreviewUrl, generatePublicShareDownloadUrl, generatePublicSharePreviewUrl, generateRecentFilesInFolderSearch } from '../davUtils.ts'

vi.mock('@nextcloud/files/dav', async (importOriginal) => ({
	...(await importOriginal()),
	getDavNameSpaces: vi.fn(() => 'xmlns:d="DAV:"'),
	getDavProperties: vi.fn(() => '<d:getlastmodified />'),
}))

vi.mock('@nextcloud/auth', async (importOriginal) => ({
	...(await importOriginal()),
	getCurrentUser: vi.fn(() => ({ uid: 'alice', displayName: 'Alice', isAdmin: false })),
}))

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
			[123, { x: 32, y: 32 }, '/core/preview?fileId=123&x=32&y=32&a=true'],
			['123', { x: -1, y: 384 }, '/core/preview?fileId=123&x=-1&y=384&a=true'],
			[123, { x: -1, y: 384, etag: 'abc123' }, '/core/preview?fileId=123&x=-1&y=384&a=true&etag=abc123'],
		])('generates preview link for %s and %o', (fileId, size, expected) => {
			expect(generatePreviewUrl(fileId, size)).toBe(generateUrl(expected))
		})
	})

	describe('generatePublicSharePreviewUrl', () => {
		it.each([
			['https://nextcloud.ltd/index.php/s/TOKEN', 'photo 1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=&x=-1&y=384&a=true'],
			['https://nextcloud.ltd/index.php/s/TOKEN/', 'photo 1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=&x=-1&y=384&a=true'],
			['https://nextcloud.ltd/index.php/s/TOKEN', '/photo 1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=%2Fphoto%201.jpg&x=-1&y=384&a=true'],
			['https://nextcloud.ltd/index.php/s/TOKEN?path=%2FMedia', '/Media/#1.jpg', '/apps/files_sharing/publicpreview/TOKEN?file=%2FMedia%2F%231.jpg&x=-1&y=384&a=true'],
		])('generates preview link for %s and %s', (link, path, expected) => {
			expect(generatePublicSharePreviewUrl(link, path, { x: -1, y: 384 })).toBe(generateUrl(expected))
		})

		it('adds etag to the preview link', () => {
			expect(generatePublicSharePreviewUrl('https://nextcloud.ltd/index.php/s/TOKEN', '/photo.jpg', { x: -1, y: 384, etag: 'abc123' }))
				.toBe(generateUrl('/apps/files_sharing/publicpreview/TOKEN?file=%2Fphoto.jpg&x=-1&y=384&a=true&etag=abc123'))
		})
	})

	describe('generateRecentFilesInFolderSearch', () => {
		it('limits the search to the folder, newest files first', () => {
			const search = generateRecentFilesInFolderSearch('/Team A/Docs', 20)
			expect(search).toContain('<d:href>/files/alice/Team%20A/Docs</d:href>')
			expect(search).toContain('<d:depth>infinity</d:depth>')
			expect(search).toContain('<d:literal>httpd/unix-directory</d:literal>')
			expect(search).toContain('<d:descending/>')
			expect(search).toContain('<d:nresults>20</d:nresults>')
		})

		it('requires a user', () => {
			vi.mocked(getCurrentUser).mockReturnValueOnce(null)
			expect(() => generateRecentFilesInFolderSearch('Team A', 5)).toThrow(TypeError)
		})
	})
})
