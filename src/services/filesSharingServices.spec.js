import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { shareFile, shareMultipleFiles } from './filesSharingServices.js'

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

	test('shareMultipleFiles calls the spreed API endpoint', () => {
		shareMultipleFiles('XXTOKENXX', ['1', '2', '3'], 'text caption', 'Display name', 'long_hash_string')

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX/share-files'),
			{
				shareIds: ['1', '2', '3'],
				caption: 'text caption',
				actorDisplayName: 'Display name',
				referenceId: 'long_hash_string',
			}
		)
	})
})
