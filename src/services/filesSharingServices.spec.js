/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { shareFile } from './filesSharingServices.js'

jest.mock('@nextcloud/axios', () => ({
	post: jest.fn(),
}))

describe('filesSharingServices', () => {
	afterEach(() => {
		// cleaning up the mess left behind the previous test
		jest.clearAllMocks()
	})

	test('shareFile calls the sharing API endpoint', () => {
		shareFile('path/to/file', 'XXTOKENXX', 'the-reference-id')

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/files_sharing/api/v1/shares'),
			{
				shareType: 10,
				shareWith: 'XXTOKENXX',
				path: 'path/to/file',
				referenceId: 'the-reference-id',
			}
		)
	})
})
