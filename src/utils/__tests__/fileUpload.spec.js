/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	getFileExtension,
	getFileSuffix,
	extractFileName,
	getFileNamePrompt,
	findUniquePath,
	hasDuplicateUploadNames,
	separateDuplicateUploads,
} from '../fileUpload.js'

const client = {
	exists: jest.fn(),
}

describe('fileUpload', () => {
	describe('getFileExtension', () => {
		it('should return correct file extension when it exists in the path', () => {
			const path = 'file.mock.txt'
			const extension = getFileExtension(path)
			expect(extension).toBe('.txt')
		})

		it('should return an empty string when no file extension exists in the path', () => {
			const path = 'file'
			const extension = getFileExtension(path)
			expect(extension).toBe('')
		})
	})

	describe('getFileSuffix', () => {
		it('should return the file suffix when it exists in the path', () => {
			const path = 'file (3).txt'
			const suffix = getFileSuffix(path)
			expect(suffix).toBe(3)
		})

		it('should return 1 when no file suffix exists in the path', () => {
			const path = 'file.txt'
			const suffix = getFileSuffix(path)
			expect(suffix).toBe(1)
		})
	})

	describe('extractFileName', () => {
		it('should return the file name as-is when there is no extension or digit suffix', () => {
			const path = 'file'
			const fileName = extractFileName(path)
			expect(fileName).toBe('file')
		})

		it('should return the correctly extracted file name without extension and digit suffix', () => {
			const paths = ['file (1).txt', 'file (1)', 'file.txt', 'file (10).txt', 'file 1.txt', 'file (1) (2).txt', 'file (N).txt']
			const fileNames = paths.map(path => extractFileName(path))
			expect(fileNames).toStrictEqual(['file', 'file', 'file', 'file', 'file 1', 'file (1)', 'file (N)'])
		})
	})

	describe('getFileNamePrompt', () => {
		it('should return the file name prompt including extension', () => {
			const path = 'file.mock.txt'
			const fileNamePrompt = getFileNamePrompt(path)
			expect(fileNamePrompt).toBe('file.mock.txt')
		})

		it('should return the file name prompt without extension when the extension is missing', () => {
			const path = 'file'
			const fileNamePrompt = getFileNamePrompt(path)
			expect(fileNamePrompt).toBe('file')
		})

		it('should return the file name prompt with digit suffix when the suffix exists', () => {
			const path = 'file (1).txt'
			const fileNamePrompt = getFileNamePrompt(path)
			expect(fileNamePrompt).toBe('file.txt')
		})
	})

	describe('findUniquePath', () => {
		const userRoot = '/files/userid/'
		const path = 'file.txt'

		afterEach(() => {
			jest.clearAllMocks()
		})

		it('should return the input path if it does not exist in the destination folder', async () => {
			// Arrange
			client.exists.mockResolvedValue(false) // Simulate resolving unique path on 1st attempt

			// Act
			const result = await findUniquePath(client, userRoot, path)

			// Assert
			expect(result).toStrictEqual({ uniquePath: path, suffix: 1 })
			expect(client.exists).toHaveBeenCalledWith(userRoot + path)
		})

		it('should return a unique path when the input path already exists in the destination folder', async () => {
			// Arrange
			const existingPath = 'file (2).txt'
			const uniquePath = 'file (3).txt'
			client.exists
				.mockResolvedValueOnce(true)
				.mockResolvedValueOnce(true)
				.mockResolvedValueOnce(false) // Simulate resolving unique path on 3rd attempt

			// Act
			const result = await findUniquePath(client, userRoot, path)

			// Assert
			expect(result).toStrictEqual({ uniquePath, suffix: 3 })
			expect(client.exists).toHaveBeenNthCalledWith(1, userRoot + path)
			expect(client.exists).toHaveBeenNthCalledWith(2, userRoot + existingPath)
			expect(client.exists).toHaveBeenNthCalledWith(3, userRoot + uniquePath)
		})

		it('should look for unique path starting from a known suffix when the input path already exists in the destination folder', async () => {
			// Arrange
			const givenPath = 'file (4).txt'
			const existingPath = 'file (5).txt'
			const uniquePath = 'file (6).txt'
			client.exists
				.mockResolvedValueOnce(true)
				.mockResolvedValueOnce(true)
				.mockResolvedValueOnce(false) // Simulate resolving unique path on 3rd attempt

			// Act
			const result = await findUniquePath(client, userRoot, givenPath)

			// Assert
			expect(result).toStrictEqual({ uniquePath, suffix: 6 })
			expect(client.exists).toHaveBeenNthCalledWith(1, userRoot + givenPath)
			expect(client.exists).toHaveBeenNthCalledWith(2, userRoot + existingPath)
			expect(client.exists).toHaveBeenNthCalledWith(3, userRoot + uniquePath)
		})
	})
})

describe('hasDuplicateUploadNames', () => {
	it('should return true when array includes duplicate upload names', () => {
		const uploads = [
			[1, { file: { name: 'file.txt' } }],
			[2, { file: { name: 'file (1).txt' } }],
			[3, { file: { name: 'file (copy).txt', newName: 'file (2).txt' } }],
		]

		expect(hasDuplicateUploadNames(uploads)).toBe(true)
	})

	it('should return false when array does not include duplicate upload names', () => {
		const uploads = [
			[1, { file: { name: 'file.txt' } }],
			[2, { file: { name: 'file 2.txt' } }],
			[3, { file: { name: 'file.txt', newName: 'file (copy).txt' } }],
		]

		expect(hasDuplicateUploadNames(uploads)).toBe(false)
	})

	describe('separateDuplicateUploads', () => {
		it('should separate unique and duplicate uploads based on file name prompt', () => {
			const uploads = [
				[1, { file: { name: 'file.txt' } }],
				[2, { file: { name: 'file (1).jpeg' } }],
				[3, { file: { name: 'file (copy).txt', newName: 'file (2).txt' } }],
				[4, { file: { name: 'file (unique).txt' } }],
			]

			const { uniques, duplicates } = separateDuplicateUploads(uploads)

			expect(uniques).toEqual([
				[1, { file: { name: 'file.txt' } }],
				[2, { file: { name: 'file (1).jpeg' } }],
				[4, { file: { name: 'file (unique).txt' } }],
			])

			expect(duplicates).toEqual([
				[3, { file: { name: 'file (copy).txt', newName: 'file (2).txt' } }],
			])
		})
	})
})
