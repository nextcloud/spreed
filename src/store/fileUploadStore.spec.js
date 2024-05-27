/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue } from '@vue/test-utils'
import mockConsole from 'jest-mock-console'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex from 'vuex'

// import { showError } from '@nextcloud/dialogs'
// import { getUploader } from '@nextcloud/upload'

// eslint-disable-next-line no-unused-vars -- required for testing
import storeConfig from './storeConfig.js'
// eslint-disable-next-line import/order -- required for testing
import fileUploadStore from './fileUploadStore.js'
import { getDavClient } from '../services/DavClient.js'
import { shareFile } from '../services/filesSharingServices.js'
import { setAttachmentFolder } from '../services/settingsService.js'
import { findUniquePath } from '../utils/fileUpload.js'

jest.mock('../services/DavClient', () => ({
	getDavClient: jest.fn(),
}))
jest.mock('../utils/fileUpload', () => ({
	...jest.requireActual('../utils/fileUpload'),
	findUniquePath: jest.fn(),
}))
jest.mock('../services/filesSharingServices', () => ({
	shareFile: jest.fn(),
}))
jest.mock('../services/settingsService', () => ({
	setAttachmentFolder: jest.fn(),
}))
jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))

describe('fileUploadStore', () => {
	let localVue = null
	let storeConfig = null
	let store = null
	let mockedActions = null

	beforeEach(() => {
		let temporaryMessageCount = 0

		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())

		mockedActions = {
			createTemporaryMessage: jest.fn()
				.mockImplementation((context, { file, index, uploadId, localUrl, token }) => {
					temporaryMessageCount += 1
					return {
						id: temporaryMessageCount,
						referenceId: 'reference-id-' + temporaryMessageCount,
						token,
						messageParameters: {
							file: {
								uploadId,
								index,
								token,
								localUrl,
								file,
							},
						},
					}
				}),
			addTemporaryMessage: jest.fn(),
			markTemporaryMessageAsFailed: jest.fn(),
		}

		global.URL.createObjectURL = jest.fn().mockImplementation((file) => 'local-url:' + file.name)
		global.OC.MimeType = {
			getIconUrl: jest.fn().mockImplementation((type) => 'icon-url:' + type),
		}

		storeConfig = cloneDeep(fileUploadStore)
		storeConfig.actions = Object.assign(storeConfig.actions, mockedActions)
		storeConfig.getters.getUserId = jest.fn().mockReturnValue(() => 'current-user')
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('uploading', () => {
		let restoreConsole
		const uploadMock = jest.fn()
		const client = {
			exists: jest.fn(),
		}

		beforeEach(() => {
			storeConfig.getters.getAttachmentFolder = jest.fn().mockReturnValue(() => '/Talk')
			store = new Vuex.Store(storeConfig)
			restoreConsole = mockConsole(['error', 'debug'])
			getDavClient.mockReturnValue(client)
			// getUploader.mockReturnValue({ upload: uploadMock })
		})

		afterEach(() => {
			restoreConsole()
		})

		test('initialises upload for given files', async () => {
			const files = [
				{
					name: 'pngimage.png',
					type: 'image/png',
					size: 123,
					lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
				},
				{
					name: 'jpgimage.jpg',
					type: 'image/jpeg',
					size: 456,
					lastModified: Date.UTC(2021, 3, 26, 15, 30, 0),
				},
				{
					name: 'textfile.txt',
					type: 'text/plain',
					size: 111,
					lastModified: Date.UTC(2021, 3, 25, 15, 30, 0),
				},
			]
			const localUrls = ['local-url:pngimage.png', 'local-url:jpgimage.jpg', 'icon-url:text/plain']

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			const uploads = store.getters.getInitialisedUploads('upload-id1')
			expect(uploads).toHaveLength(files.length)

			for (const index in files) {
				expect(mockedActions.createTemporaryMessage.mock.calls[index][1]).toMatchObject({
					text: '{file}',
					token: 'XXTOKENXX',
					uploadId: 'upload-id1',
					index: expect.anything(),
					file: files[index],
					localUrl: localUrls[index],
				})
			}
		})

		test('performs silent upload and sharing of single file with caption', async () => {
			const file = {
				name: 'pngimage.png',
				type: 'image/png',
				size: 123,
				lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
			}

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files: [file],
			})

			expect(store.getters.currentUploadId).toBe('upload-id1')

			const uniqueFileName = '/Talk/' + file.name + 'uniq'
			findUniquePath.mockResolvedValueOnce({ uniquePath: uniqueFileName, suffix: 1 })
			uploadMock.mockResolvedValue()
			shareFile.mockResolvedValue()

			await store.dispatch('uploadFiles', { token: 'XXTOKENXX', uploadId: 'upload-id1', caption: 'text-caption', options: { silent: true } })

			expect(findUniquePath).toHaveBeenCalledTimes(1)
			expect(findUniquePath).toHaveBeenCalledWith(client, '/files/current-user', '/Talk/' + file.name, undefined)

			expect(uploadMock).toHaveBeenCalledTimes(1)
			expect(uploadMock).toHaveBeenCalledWith(uniqueFileName, file)

			expect(shareFile).toHaveBeenCalledTimes(1)
			expect(shareFile).toHaveBeenCalledWith(uniqueFileName, 'XXTOKENXX', 'reference-id-1', '{"caption":"text-caption","silent":true}')

			expect(mockedActions.addTemporaryMessage).toHaveBeenCalledTimes(1)
			expect(store.getters.currentUploadId).not.toBeDefined()
		})

		test('performs upload and sharing of multiple files with caption', async () => {
			const file1 = {
				name: 'pngimage.png',
				type: 'image/png',
				size: 123,
				lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
			}
			const file2 = {
				name: 'textfile.txt',
				type: 'text/plain',
				size: 111,
				lastModified: Date.UTC(2021, 3, 25, 15, 30, 0),
			}
			const files = [file1, file2]

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			expect(store.getters.currentUploadId).toBe('upload-id1')

			findUniquePath
				.mockResolvedValueOnce({ uniquePath: '/Talk/' + files[0].name + 'uniq', suffix: 1 })
				.mockResolvedValueOnce({ uniquePath: '/Talk/' + files[1].name + 'uniq', suffix: 1 })
			shareFile
				.mockResolvedValueOnce({ data: { ocs: { data: { id: '1' } } } })
				.mockResolvedValueOnce({ data: { ocs: { data: { id: '2' } } } })

			await store.dispatch('uploadFiles', { token: 'XXTOKENXX', uploadId: 'upload-id1', caption: 'text-caption', options: { silent: false } })

			expect(findUniquePath).toHaveBeenCalledTimes(2)
			expect(uploadMock).toHaveBeenCalledTimes(2)
			for (const index in files) {
				expect(findUniquePath).toHaveBeenNthCalledWith(+index + 1, client, '/files/current-user', '/Talk/' + files[index].name, undefined)
				expect(uploadMock).toHaveBeenNthCalledWith(+index + 1, `/Talk/${files[index].name}uniq`, files[index])
			}

			expect(shareFile).toHaveBeenCalledTimes(2)
			expect(shareFile).toHaveBeenNthCalledWith(1, '/Talk/' + files[0].name + 'uniq', 'XXTOKENXX', 'reference-id-1', '{}')
			expect(shareFile).toHaveBeenNthCalledWith(2, '/Talk/' + files[1].name + 'uniq', 'XXTOKENXX', 'reference-id-2', '{"caption":"text-caption"}')

			expect(mockedActions.addTemporaryMessage).toHaveBeenCalledTimes(2)
			expect(store.getters.currentUploadId).not.toBeDefined()
		})

		test('marks temporary message as failed in case of upload error', async () => {
			const files = [
				{
					name: 'pngimage.png',
					type: 'image/png',
					size: 123,
					lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
				},
			]

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			findUniquePath
				.mockResolvedValueOnce({ uniquePath: '/Talk/' + files[0].name + 'uniq', suffix: 1 })
			uploadMock.mockRejectedValueOnce({
				response: {
					status: 403,
				},
			})
			await store.dispatch('uploadFiles', { token: 'XXTOKENXX', uploadId: 'upload-id1', options: { silent: false } })

			expect(uploadMock).toHaveBeenCalledTimes(1)
			expect(shareFile).not.toHaveBeenCalled()

			expect(mockedActions.addTemporaryMessage).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed).toHaveBeenCalledWith(expect.anything(), {
				token: 'XXTOKENXX',
				id: 1,
				uploadId: 'upload-id1',
				reason: 'failed-upload'
			})
			// expect(showError).toHaveBeenCalled()
			expect(console.error).toHaveBeenCalled()
		})

		test('marks temporary message as failed in case of sharing error', async () => {
			const files = [
				{
					name: 'pngimage.png',
					type: 'image/png',
					size: 123,
					lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
				},
			]

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			findUniquePath
				.mockResolvedValueOnce('/Talk/' + files[0].name + 'uniq')
			shareFile.mockRejectedValueOnce({
				response: {
					status: 403,
				},
			})

			await store.dispatch('uploadFiles', { token: 'XXTOKENXX', uploadId: 'upload-id1', options: { silent: false } })

			expect(uploadMock).toHaveBeenCalledTimes(1)
			expect(shareFile).toHaveBeenCalledTimes(1)

			expect(mockedActions.addTemporaryMessage).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed).toHaveBeenCalledWith(expect.anything(), {
				token: 'XXTOKENXX',
				id: 1,
				uploadId: 'upload-id1',
				reason: 'failed-share'
			})
			// expect(showError).toHaveBeenCalled()
			expect(console.error).toHaveBeenCalled()
		})

		test('removes file from selection', async () => {
			const files = [
				{
					name: 'pngimage.png',
					type: 'image/png',
					size: 123,
					lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
				},
				{
					name: 'textfile.txt',
					type: 'text/plain',
					size: 111,
					lastModified: Date.UTC(2021, 3, 25, 15, 30, 0),
				},
			]

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			// temporary message mock uses incremental id
			await store.dispatch('removeFileFromSelection', 2)

			const uploads = store.getters.getInitialisedUploads('upload-id1')
			expect(uploads).toHaveLength(1)

			expect(uploads[0][1].file).toStrictEqual(files[0])
		})

		test('discard an entire upload', async () => {
			const files = [
				{
					name: 'pngimage.png',
					type: 'image/png',
					size: 123,
					lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
				},
				{
					name: 'textfile.txt',
					type: 'text/plain',
					size: 111,
					lastModified: Date.UTC(2021, 3, 25, 15, 30, 0),
				},
			]

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			await store.dispatch('discardUpload', 'upload-id1')

			const uploads = store.getters.getInitialisedUploads('upload-id1')
			expect(uploads).toStrictEqual([])

			expect(store.getters.currentUploadId).not.toBeDefined()
		})

		test('autorenames files using timestamps when requested', async () => {
			const files = [
				{
					name: 'pngimage.png',
					type: 'image/png',
					size: 123,
					lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
				},
				{
					name: 'textfile.txt',
					type: 'text/plain',
					size: 111,
					lastModified: Date.UTC(2021, 3, 25, 15, 30, 0),
				},
			]

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
				rename: true,
			})

			expect(files[0].newName).toBe('20210427_153000.png')
			expect(files[1].newName).toBe('20210425_153000.txt')
		})
	})

	test('set attachment folder', async () => {
		store = new Vuex.Store(storeConfig)

		setAttachmentFolder.mockResolvedValue()
		await store.dispatch('setAttachmentFolder', '/Talk-another')

		expect(setAttachmentFolder).toHaveBeenCalledWith('/Talk-another')
		expect(store.getters.getAttachmentFolder()).toBe('/Talk-another')
	})
})
