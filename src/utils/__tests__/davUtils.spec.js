/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl, getBaseUrl } from '@nextcloud/router'
import { describe, expect, it } from 'vitest'
import { generatePreviewUrl, generatePublicShareDownloadUrl, generatePublicSharePreviewUrl, isFederatedShareLink } from '../davUtils.ts'

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

		it.each([
			['https://host.ltd/index.php/s/TOKEN?talkFederationAuth=42.abc', '/photo 1.jpg', 'https://host.ltd/public.php/dav/files/TOKEN/photo%201.jpg?talkFederationAuth=42.abc'],
			['https://host.ltd/nc/s/TOKEN?path=%2FMedia&talkFederationAuth=42.abc', '/Media/#1.jpg', 'https://host.ltd/nc/public.php/dav/files/TOKEN/Media/%231.jpg?talkFederationAuth=42.abc'],
		])('generates federated download link for %s and %s', (link, path, expected) => {
			expect(generatePublicShareDownloadUrl(link, path)).toBe(expected)
		})
	})

	describe('isFederatedShareLink', () => {
		it.each([
			[undefined, false],
			['https://nextcloud.ltd/index.php/s/TOKEN', false],
			['https://host.ltd/index.php/s/TOKEN?talkFederationAuth=42.abc', true],
		])('detects %s', (link, expected) => {
			expect(isFederatedShareLink(link)).toBe(expected)
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

		it('generates federated preview link on the host server', () => {
			expect(generatePublicSharePreviewUrl('https://host.ltd/nc/index.php/s/TOKEN?talkFederationAuth=42.abc', '/photo.jpg', { x: -1, y: 384 }))
				.toBe(generateUrl('/apps/files_sharing/publicpreview/TOKEN?file=%2Fphoto.jpg&x=-1&y=384&a=1', {}, { baseURL: 'https://host.ltd/nc' }) + '&talkFederationAuth=42.abc')
		})
	})
})
