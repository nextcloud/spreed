/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { afterEach, describe, expect, test, vi } from 'vitest'
import { postAttachment, shareFile } from '../filesSharingServices.ts'

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

describe('filesSharingServices', () => {
	afterEach(() => {
		// cleaning up the mess left behind the previous test
		vi.clearAllMocks()
	})

	test('shareFile calls the sharing API endpoint', () => {
		shareFile({
			path: 'path/to/file',
			shareWith: 'XXTOKENXX',
			referenceId: 'the-reference-id',
		})

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/files_sharing/api/v1/shares'),
			{
				shareType: 10,
				shareWith: 'XXTOKENXX',
				path: 'path/to/file',
				referenceId: 'the-reference-id',
			},
		)
	})

	test('postAttachment calls the Talk chat attachment API endpoint', async () => {
		axios.post.mockResolvedValue({ data: { ocs: { data: { renames: [{ 'test.txt': 'test.txt' }] } } } })

		const renames = await postAttachment({
			token: 'XXTOKENXX',
			filePath: 'Talk/My Room-XXTOKENXX/Current User-current-user/upload-id1-0-test.txt',
			fileName: 'test.txt',
			referenceId: 'the-reference-id',
			talkMetaData: '{"caption":"hello"}',
		})

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/{token}/attachment', { token: 'XXTOKENXX' }),
			{
				filePath: 'Talk/My Room-XXTOKENXX/Current User-current-user/upload-id1-0-test.txt',
				fileName: 'test.txt',
				referenceId: 'the-reference-id',
				talkMetaData: '{"caption":"hello"}',
			},
		)
		expect(renames).toEqual([{ 'test.txt': 'test.txt' }])
	})

	test('postAttachment returns conflict-resolved renames when backend renames the file', async () => {
		axios.post.mockResolvedValue({
			data: { ocs: { data: { renames: [{ 'photo.jpg': 'photo (1).jpg' }] } } },
		})

		const renames = await postAttachment({
			token: 'XXTOKENXX',
			filePath: 'Talk/Room-XXTOKENXX/Alice-alice/upload-id1-0-photo.jpg',
			fileName: 'photo.jpg',
			referenceId: 'ref-1',
			talkMetaData: '{}',
		})

		expect(renames).toEqual([{ 'photo.jpg': 'photo (1).jpg' }])
	})

	test('postAttachment returns empty array when response has no renames field', async () => {
		axios.post.mockResolvedValue({})

		const renames = await postAttachment({
			token: 'XXTOKENXX',
			filePath: 'Talk/Room-XXTOKENXX/Alice-alice/upload-id1-0-doc.pdf',
			fileName: 'doc.pdf',
			referenceId: 'ref-2',
			talkMetaData: '{}',
		})

		expect(renames).toEqual([])
	})
})
