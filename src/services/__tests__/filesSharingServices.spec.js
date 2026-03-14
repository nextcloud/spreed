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

	test('postAttachment calls the Talk attachment API endpoint', async () => {
		axios.post.mockResolvedValue({})

		await postAttachment({
			token: 'XXTOKENXX',
			filePath: 'Talk/My Room-XXTOKENXX/Current User-current-user/test.txt',
			referenceId: 'the-reference-id',
			talkMetaData: '{"caption":"hello"}',
		})

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/room/{token}/attachment', { token: 'XXTOKENXX' }),
			{
				filePath: 'Talk/My Room-XXTOKENXX/Current User-current-user/test.txt',
				referenceId: 'the-reference-id',
				talkMetaData: '{"caption":"hello"}',
			},
		)
	})
})
