import { createLocalVue } from '@vue/test-utils'
import mockConsole from 'jest-mock-console'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import { showError } from '@nextcloud/dialogs'

import { getDavClient } from '../services/DavClient.js'
import { shareFile } from '../services/filesSharingServices.js'
import { setAttachmentFolder } from '../services/settingsService.js'
import { findUniquePath, getFileExtension } from '../utils/fileUpload.js'
import fileUploadStore from './fileUploadStore.js'

jest.mock('../services/DavClient', () => ({
	getDavClient: jest.fn(),
}))
jest.mock('../utils/fileUpload', () => ({
	findUniquePath: jest.fn(),
	getFileExtension: jest.fn(),
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
		const client = {
			putFileContents: jest.fn(),
		}

		beforeEach(() => {
			storeConfig.getters.getAttachmentFolder = jest.fn().mockReturnValue(() => '/Talk')
			store = new Vuex.Store(storeConfig)
			restoreConsole = mockConsole(['error', 'debug'])
			getDavClient.mockReturnValue(client)
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
			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			const uploads = store.getters.getInitialisedUploads('upload-id1')
			expect(Object.keys(uploads).length).toBe(3)

			for (let i = 0; i < files.length; i++) {
				expect(mockedActions.createTemporaryMessage.mock.calls[i][1].text).toBe('{file}')
				expect(mockedActions.createTemporaryMessage.mock.calls[i][1].uploadId).toBe('upload-id1')
				expect(mockedActions.createTemporaryMessage.mock.calls[i][1].index).toBeDefined()
				expect(mockedActions.createTemporaryMessage.mock.calls[i][1].file).toBe(files[i])
				expect(mockedActions.createTemporaryMessage.mock.calls[i][1].token).toBe('XXTOKENXX')
			}

			expect(mockedActions.createTemporaryMessage.mock.calls[0][1].localUrl).toBe('local-url:pngimage.png')
			expect(mockedActions.createTemporaryMessage.mock.calls[1][1].localUrl).toBe('local-url:jpgimage.jpg')
			expect(mockedActions.createTemporaryMessage.mock.calls[2][1].localUrl).toBe('icon-url:text/plain')
		})

		test('performs upload by uploading then sharing', async () => {
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
			const fileBuffers = [
				await new Blob([file1]).arrayBuffer(),
				await new Blob([file2]).arrayBuffer(),
			]

			await store.dispatch('initialiseUpload', {
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			expect(store.getters.currentUploadId).toBe('upload-id1')

			findUniquePath
				.mockResolvedValueOnce('/Talk/' + files[0].name + 'uniq')
				.mockResolvedValueOnce('/Talk/' + files[1].name + 'uniq')
			shareFile.mockResolvedValue()

			await store.dispatch('uploadFiles', 'upload-id1')

			expect(client.putFileContents).toHaveBeenCalledTimes(2)
			expect(shareFile).toHaveBeenCalledTimes(2)

			for (let i = 0; i < files.length; i++) {
				expect(findUniquePath).toHaveBeenCalledWith(client, '/files/current-user', '/Talk/' + files[i].name)
				expect(client.putFileContents.mock.calls[i][0]).toBe('/files/current-user/Talk/' + files[i].name + 'uniq')
				expect(client.putFileContents.mock.calls[i][1]).toStrictEqual(fileBuffers[i])

				expect(shareFile.mock.calls[i][0]).toBe('//Talk/' + files[i].name + 'uniq')
				expect(shareFile.mock.calls[i][1]).toBe('XXTOKENXX')
				expect(shareFile.mock.calls[i][2]).toBe('reference-id-' + (i + 1))
			}

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
				.mockResolvedValueOnce('/Talk/' + files[0].name + 'uniq')
			client.putFileContents.mockRejectedValueOnce({
				response: {
					status: 403,
				},
			})

			await store.dispatch('uploadFiles', 'upload-id1')

			expect(client.putFileContents).toHaveBeenCalledTimes(1)
			expect(shareFile).not.toHaveBeenCalled()

			expect(mockedActions.addTemporaryMessage).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed.mock.calls[0][1].message.referenceId).toBe('reference-id-1')
			expect(mockedActions.markTemporaryMessageAsFailed.mock.calls[0][1].reason).toBe('failed-upload')
			expect(showError).toHaveBeenCalled()
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

			await store.dispatch('uploadFiles', 'upload-id1')

			expect(client.putFileContents).toHaveBeenCalledTimes(1)
			expect(shareFile).toHaveBeenCalledTimes(1)

			expect(mockedActions.addTemporaryMessage).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed).toHaveBeenCalledTimes(1)
			expect(mockedActions.markTemporaryMessageAsFailed.mock.calls[0][1].message.referenceId).toBe('reference-id-1')
			expect(mockedActions.markTemporaryMessageAsFailed.mock.calls[0][1].reason).toBe('failed-share')
			expect(showError).toHaveBeenCalled()
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
			expect(Object.keys(uploads).length).toBe(1)

			expect(Object.values(uploads)[0].file).toBe(files[0])
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
			expect(uploads).toStrictEqual({})

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

			getFileExtension
				.mockReturnValueOnce('.png')
				.mockReturnValueOnce('.txt')

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
