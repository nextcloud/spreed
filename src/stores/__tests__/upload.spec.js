/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { getUploader } from '@nextcloud/upload'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { getDavClient } from '../../services/DavClient.ts'
import { postAttachment, probeAttachmentFolder, shareFile } from '../../services/filesSharingServices.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { findUniquePath } from '../../utils/fileUpload.ts'
import { useActorStore } from '../actor.ts'
import { useSettingsStore } from '../settings.ts'
import { useUploadStore } from '../upload.ts'

// conversationGetter must be defined before vi.mock so the factory can close over it.
// Vitest evaluates the factory lazily (after module-level init), so this works.
const conversationGetter = vi.fn().mockReturnValue(null)
const vuexStoreDispatch = vi.fn()
vi.mock('vuex', () => ({
	useStore: vi.fn(() => ({
		getters: { conversation: conversationGetter },
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
	postAttachment: vi.fn(),
	probeAttachmentFolder: vi.fn(),
	shareFile: vi.fn(),
}))
vi.mock('../../services/CapabilitiesManager.ts', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		getTalkConfig: vi.fn().mockReturnValue(true),
	}
})

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
			createDirectory: vi.fn().mockResolvedValue(undefined),
			exists: vi.fn(),
		}

		beforeEach(() => {
			getDavClient.mockReturnValue(client)
			getUploader.mockReturnValue({ upload: uploadMock })
			console.error = vi.fn()
			// Default: ONE_TO_ONE room — no conversation folder used
			conversationGetter.mockReturnValue({ type: 1, displayName: 'Direct message' })
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

		describe('conversation folder (group/public rooms)', () => {
			const TOKEN = 'XXTOKENXX'
			const DRAFT_PATH = 'Talk/My Room-XXTOKENXX/Draft'

			beforeEach(() => {
				uploadMock.mockResolvedValue()
				postAttachment.mockResolvedValue()
				probeAttachmentFolder.mockResolvedValue(generateOCSResponse({ payload: { folder: DRAFT_PATH, renames: [] } }))
			})

			test('probes the attachment folder and posts via postAttachment for a group room', async () => {
				conversationGetter.mockReturnValue({ type: 2, displayName: 'My Room' })

				const file = {
					name: 'pngimage.png',
					type: 'image/png',
					size: 123,
					lastModified: Date.UTC(2021, 3, 27, 15, 30, 0),
				}

				uploadStore.initialiseUpload({ uploadId: 'upload-id1', token: TOKEN, files: [file] })

				await uploadStore.uploadFiles({ token: TOKEN, uploadId: 'upload-id1', options: { silent: false } })

				// Probe endpoint is called with the desired file names;
				// folder creation and the TYPE_ROOM share happen server-side.
				expect(probeAttachmentFolder).toHaveBeenCalledTimes(1)
				expect(probeAttachmentFolder).toHaveBeenCalledWith({
					token: TOKEN,
					fileNames: [file.name],
				})

				// No client-side MKCOL and no PROPFIND round-trip.
				expect(client.createDirectory).not.toHaveBeenCalled()
				expect(findUniquePath).not.toHaveBeenCalled()

				// File is uploaded under a random UUID temp name inside the Draft folder.
				expect(uploadMock).toHaveBeenCalledTimes(1)
				const uploadedPath = uploadMock.mock.calls[0][0]
				const uuidRe = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
				expect(uploadedPath).toMatch(new RegExp('^/' + DRAFT_PATH + '/' + uuidRe + '$'))

				// File is posted via the Talk attachment endpoint with the
				// original name for rename-on-conflict on the backend.
				expect(postAttachment).toHaveBeenCalledTimes(1)
				expect(postAttachment).toHaveBeenCalledWith(expect.objectContaining({
					token: TOKEN,
					fileName: file.name,
					filePath: expect.stringMatching(new RegExp('^' + DRAFT_PATH + '/' + uuidRe + '$')),
				}))
				expect(shareFile).not.toHaveBeenCalled()
			})

			test('probes with all file names and posts each file in a multi-file upload', async () => {
				conversationGetter.mockReturnValue({ type: 2, displayName: 'Room' })

				const file1 = { name: 'photo.jpg', type: 'image/jpeg', size: 100, lastModified: 0 }
				const file2 = { name: 'doc.pdf', type: 'application/pdf', size: 200, lastModified: 0 }

				uploadStore.initialiseUpload({ uploadId: 'upload-id1', token: TOKEN, files: [file1, file2] })

				await uploadStore.uploadFiles({ token: TOKEN, uploadId: 'upload-id1', options: null })

				expect(probeAttachmentFolder).toHaveBeenCalledWith({
					token: TOKEN,
					fileNames: ['photo.jpg', 'doc.pdf'],
				})
				expect(findUniquePath).not.toHaveBeenCalled()
				expect(postAttachment).toHaveBeenCalledTimes(2)
				expect(postAttachment).toHaveBeenCalledWith(expect.objectContaining({ fileName: 'photo.jpg' }))
				expect(postAttachment).toHaveBeenCalledWith(expect.objectContaining({ fileName: 'doc.pdf' }))
			})

			test('updates temporary message file names when probe predicts renames', async () => {
				conversationGetter.mockReturnValue({ type: 2, displayName: 'Room' })
				probeAttachmentFolder.mockResolvedValue(generateOCSResponse({ payload: {
					folder: DRAFT_PATH,
					renames: [
						{ 'photo.jpg': 'photo (1).jpg' },
						{ 'doc.pdf': 'doc.pdf' },
					],
				}}))

				const file1 = { name: 'photo.jpg', type: 'image/jpeg', size: 100, lastModified: 0 }
				const file2 = { name: 'doc.pdf', type: 'application/pdf', size: 200, lastModified: 0 }

				uploadStore.initialiseUpload({ uploadId: 'upload-id1', token: TOKEN, files: [file1, file2] })

				await uploadStore.uploadFiles({ token: TOKEN, uploadId: 'upload-id1', options: null })

				// Only the renamed file should trigger a dispatch
				const updateCalls = vuexStoreDispatch.mock.calls
					.filter(([action]) => action === 'addTemporaryMessage')

				const expectedFileNameMatch = (name) => expect.objectContaining({
					messageParameters: expect.objectContaining({
						file: expect.objectContaining({
							name,
						}),
					}),
				})

				expect(updateCalls).toHaveLength(3)
				// Initial name assignment
				expect(updateCalls[0][1]).toMatchObject({
					token: TOKEN,
					message: expectedFileNameMatch('photo.jpg'),
				})
				expect(updateCalls[1][1]).toMatchObject({
					token: TOKEN,
					message: expectedFileNameMatch('doc.pdf'),
				})
				// Rename from probeAttachment
				expect(updateCalls[2][1]).toMatchObject({
					token: TOKEN,
					message: expectedFileNameMatch('photo (1).jpg'),
				})
			})

			test('falls back to shareFile when the probe endpoint fails', async () => {
				conversationGetter.mockReturnValue({ type: 2, displayName: 'My Room' })
				probeAttachmentFolder.mockRejectedValueOnce(new Error('boom'))
				findUniquePath.mockResolvedValueOnce({ path: '/Talk/photo.jpg', name: 'photo.jpg' })

				const file = { name: 'photo.jpg', type: 'image/jpeg', size: 100, lastModified: 0 }
				uploadStore.initialiseUpload({ uploadId: 'upload-id1', token: TOKEN, files: [file] })

				await uploadStore.uploadFiles({ token: TOKEN, uploadId: 'upload-id1', options: null })

				expect(probeAttachmentFolder).toHaveBeenCalledTimes(1)
				expect(postAttachment).not.toHaveBeenCalled()
				expect(shareFile).toHaveBeenCalledTimes(1)
			})

			test('falls back to shareFile when conversation-subfolders capability is false', async () => {
				getTalkConfig.mockReturnValueOnce(false)
				conversationGetter.mockReturnValue({ type: 2, displayName: 'My Room' })
				findUniquePath.mockResolvedValueOnce({ path: '/Talk/photo.jpg', name: 'photo.jpg' })

				const file = { name: 'photo.jpg', type: 'image/jpeg', size: 100, lastModified: 0 }
				uploadStore.initialiseUpload({ uploadId: 'upload-id1', token: TOKEN, files: [file] })

				await uploadStore.uploadFiles({ token: TOKEN, uploadId: 'upload-id1', options: null })

				expect(probeAttachmentFolder).not.toHaveBeenCalled()
				expect(postAttachment).not.toHaveBeenCalled()
				expect(shareFile).toHaveBeenCalledTimes(1)
			})
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
