import mockAxios from '../__mocks__/axios.js'
import { generateOcsUrl } from '@nextcloud/router'
import { shareFile } from './filesSharingServices.js'

describe('filesSharingServices', () => {
	afterEach(() => {
		// cleaning up the mess left behind the previous test
		mockAxios.reset()
	})

	test('shareFile calls the sharing API endpoint', () => {
		shareFile('path/to/file', 'XXTOKENXX', 'the-reference-id')

		expect(mockAxios.post).toHaveBeenCalledWith(
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
