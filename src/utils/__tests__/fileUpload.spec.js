import {
	getFileExtension,
	extractFileName,
	findUniquePath,
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
			expect(result).toBe(path)
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
			expect(result).toBe(uniquePath)
			expect(client.exists).toHaveBeenNthCalledWith(1, userRoot + path)
			expect(client.exists).toHaveBeenNthCalledWith(2, userRoot + existingPath)
			expect(client.exists).toHaveBeenNthCalledWith(3, userRoot + uniquePath)
		})
	})
})
