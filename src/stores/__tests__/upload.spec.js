/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { getUploader } from '@nextcloud/upload'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { getDavClient } from '../../services/DavClient.ts'
import { shareFile } from '../../services/filesSharingServices.ts'
import { findUniquePath } from '../../utils/fileUpload.ts'
import { useActorStore } from '../actor.ts'
import { useSettingsStore } from '../settings.ts'
import { useUploadStore } from '../upload.ts'

const vuexStoreDispatch = vi.fn()
vi.mock('vuex', () => ({
	useStore: vi.fn(() => ({
		getters: {},
		dispatch: vuexStoreDispatch,
	})),
}))

vi.mock('../../services/DavClient.ts', () => ({
	getDavClient: vi.fn(),
}))
vi.mock('../../utils/fileUpload.ts', async () => {
	const fileUpload = await vi.importActual('../../utils/fileUpload.ts')
	return {
		...fileUpload,
		findUniquePath: vi.fn(),
	}
})
vi.mock('../../services/filesSharingServices.ts', () => ({
	shareFile: vi.fn(),
}))

describe('fileUploadStore', () => {
	let actorStore
	let settingsStore
	let uploadStore

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()
		settingsStore = useSettingsStore()
		uploadStore = useUploadStore()

		global.URL.createObjectURL = vi.fn().mockImplementation((file) => 'local-url:' + file.name)

		actorStore.userId = 'current-user'
		actorStore.actorId = 'current-user'
		actorStore.actorType = 'users'
		actorStore.displayName = 'Current User'
		settingsStore.attachmentFolder = '/Talk'
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('uploading', () => {
		const uploadMock = vi.fn()
		const client = {
			exists: vi.fn(),
		}

		beforeEach(() => {
			getDavClient.mockReturnValue(client)
			getUploader.mockReturnValue({ upload: uploadMock })
			console.error = vi.fn()
		})

		afterEach(() => {
			vi.clearAllMocks()
		})

		test('initialises upload for given files', () => {
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
			const localUrls = ['local-url:pngimage.png', 'local-url:jpgimage.jpg', undefined]

			uploadStore.initialiseUpload({
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			const uploads = uploadStore.getInitialisedUploads('upload-id1')
			expect(uploads).toHaveLength(files.length)

			for (const index in uploads) {
				expect(uploads[index][1].temporaryMessage).toMatchObject({
					message: '{file}',
					token: 'XXTOKENXX',
				})
				expect(uploads[index][1].temporaryMessage.messageParameters.file).toMatchObject({
					type: 'file',
					mimetype: files[index].type,
					id: uploads[index][1].temporaryMessage.id,
					name: files[index].name,
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

			uploadStore.initialiseUpload({
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files: [file],
			})

			expect(uploadStore.currentUploadId).toBe('upload-id1')

			const uniqueFileName = '/Talk/' + file.name + 'uniq'
			const referenceId = uploadStore.getUploadsArray('upload-id1')[0][1].temporaryMessage.referenceId
			findUniquePath.mockResolvedValueOnce({ uniquePath: uniqueFileName, suffix: 1 })
			uploadMock.mockResolvedValue()
			shareFile.mockResolvedValue()

			await uploadStore.uploadFiles({ token: 'XXTOKENXX', uploadId: 'upload-id1', caption: 'text-caption', options: { silent: true } })

			expect(findUniquePath).toHaveBeenCalledTimes(1)
			expect(findUniquePath).toHaveBeenCalledWith(client, '/files/current-user', '/Talk/' + file.name, undefined)

			expect(uploadMock).toHaveBeenCalledTimes(1)
			expect(uploadMock).toHaveBeenCalledWith(uniqueFileName, file)

			expect(shareFile).toHaveBeenCalledTimes(1)
			expect(shareFile).toHaveBeenCalledWith({
				path: uniqueFileName,
				shareWith: 'XXTOKENXX',
				referenceId,
				talkMetaData: '{"caption":"text-caption","silent":true}',
			})

			expect(vuexStoreDispatch).toHaveBeenCalledWith('addTemporaryMessage', expect.anything())
			expect(uploadStore.currentUploadId).not.toBeDefined()
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

			uploadStore.initialiseUpload({
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			expect(uploadStore.currentUploadId).toBe('upload-id1')

			findUniquePath
				.mockResolvedValueOnce({ uniquePath: '/Talk/' + files[0].name + 'uniq', suffix: 1 })
				.mockResolvedValueOnce({ uniquePath: '/Talk/' + files[1].name + 'uniq', suffix: 1 })
			shareFile
				.mockResolvedValueOnce({ data: { ocs: { data: { id: '1' } } } })
				.mockResolvedValueOnce({ data: { ocs: { data: { id: '2' } } } })

			await uploadStore.uploadFiles({ token: 'XXTOKENXX', uploadId: 'upload-id1', caption: 'text-caption', options: { silent: false } })

			expect(findUniquePath).toHaveBeenCalledTimes(2)
			expect(uploadMock).toHaveBeenCalledTimes(2)
			for (const index in files) {
				expect(findUniquePath).toHaveBeenNthCalledWith(+index + 1, client, '/files/current-user', '/Talk/' + files[index].name, undefined)
				expect(uploadMock).toHaveBeenNthCalledWith(+index + 1, `/Talk/${files[index].name}uniq`, files[index])
			}
			const referenceIds = uploadStore.getUploadsArray('upload-id1').map((entry) => entry[1].temporaryMessage.referenceId)

			expect(shareFile).toHaveBeenCalledTimes(2)
			expect(shareFile).toHaveBeenNthCalledWith(1, {
				path: '/Talk/' + files[0].name + 'uniq',
				shareWith: 'XXTOKENXX',
				referenceId: referenceIds[0],
				talkMetaData: '{}',
			})
			expect(shareFile).toHaveBeenNthCalledWith(2, {
				path: '/Talk/' + files[1].name + 'uniq',
				shareWith: 'XXTOKENXX',
				referenceId: referenceIds[1],
				talkMetaData: '{"caption":"text-caption"}',
			})

			expect(vuexStoreDispatch).toHaveBeenNthCalledWith(1, 'addTemporaryMessage', expect.anything())
			expect(vuexStoreDispatch).toHaveBeenNthCalledWith(2, 'addTemporaryMessage', expect.anything())
			expect(uploadStore.currentUploadId).not.toBeDefined()
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

			uploadStore.initialiseUpload({
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
			await uploadStore.uploadFiles({ token: 'XXTOKENXX', uploadId: 'upload-id1', options: { silent: false } })

			expect(uploadMock).toHaveBeenCalledTimes(1)
			expect(shareFile).not.toHaveBeenCalled()

			expect(vuexStoreDispatch).toHaveBeenCalledTimes(2)
			expect(vuexStoreDispatch).toHaveBeenNthCalledWith(1, 'addTemporaryMessage', expect.anything())
			expect(vuexStoreDispatch).toHaveBeenNthCalledWith(2, 'markTemporaryMessageAsFailed', {
				token: 'XXTOKENXX',
				id: uploadStore.getUploadsArray('upload-id1')[0][1].temporaryMessage.id,
				uploadId: 'upload-id1',
				reason: 'failed-upload',
			})
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

			uploadStore.initialiseUpload({
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

			await uploadStore.uploadFiles({ token: 'XXTOKENXX', uploadId: 'upload-id1', options: { silent: false } })

			expect(uploadMock).toHaveBeenCalledTimes(1)
			expect(shareFile).toHaveBeenCalledTimes(1)

			expect(vuexStoreDispatch).toHaveBeenCalledTimes(2)
			expect(vuexStoreDispatch).toHaveBeenNthCalledWith(1, 'addTemporaryMessage', expect.anything())
			expect(vuexStoreDispatch).toHaveBeenNthCalledWith(2, 'markTemporaryMessageAsFailed', {
				token: 'XXTOKENXX',
				id: uploadStore.getUploadsArray('upload-id1')[0][1].temporaryMessage.id,
				uploadId: 'upload-id1',
				reason: 'failed-share',
			})
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

			uploadStore.initialiseUpload({
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			const fileIds = uploadStore.getUploadsArray('upload-id1').map((entry) => entry[1].temporaryMessage.id)
			await uploadStore.removeFileFromSelection(fileIds[1])

			const uploads = uploadStore.getInitialisedUploads('upload-id1')
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

			uploadStore.initialiseUpload({
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
			})

			await uploadStore.discardUpload('upload-id1')

			const uploads = uploadStore.getInitialisedUploads('upload-id1')
			expect(uploads).toStrictEqual([])

			expect(uploadStore.currentUploadId).not.toBeDefined()
		})

		test('autorenames files using timestamps when requested', () => {
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

			uploadStore.initialiseUpload({
				uploadId: 'upload-id1',
				token: 'XXTOKENXX',
				files,
				rename: true,
			})

			expect(files[0].newName).toBe('20210427_153000.png')
			expect(files[1].newName).toBe('20210425_153000.txt')
		})
	})
})
