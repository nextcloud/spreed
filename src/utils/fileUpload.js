/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

const extensionRegex = /\.[0-9a-z]+$/i
const suffixRegex = / \(\d+\)$/

/**
 * Returns the file extension for the given path
 *
 * @param {string} path path
 * @return {string} file extension including the dot
 */
const getFileExtension = function(path) {
	return path.match(extensionRegex) ? path.match(extensionRegex)[0] : ''
}

/**
 * Returns the file name without extension and digit suffix
 *
 * @param {string} path path
 * @return {string} extracted file name
 */
const extractFileName = function(path) {
	return path
	// If there is a file extension, remove it from the path string
		.replace(extensionRegex, '')
	// If a filename ends with suffix ` (n)`, remove it from the path string
		.replace(suffixRegex, '')
}

/**
 * Returns the file name prompt for the given path
 *
 * @param {string} path path
 * @return {string} file name prompt
 */
const getFileNamePrompt = function(path) {
	return extractFileName(path) + getFileExtension(path)
}

/**
 * Checks the existence of a path in a folder and if a match is found, returns
 * a unique path for that folder.
 *
 * @param {object} client The webdav client object
 * @param {string} userRoot user root path
 * @param {string} path The path whose existence in the destination is to
 * be checked
 * @return {string} The unique path
 */
const findUniquePath = async function(client, userRoot, path) {
	// Return the input path if it doesn't exist in the destination folder
	if (await client.exists(userRoot + path) === false) {
		return path
	}

	const fileExtension = getFileExtension(path)
	const fileName = extractFileName(path)

	// Loop until a unique path is found
	for (let number = 2; true; number++) {
		const uniquePath = fileName + ` (${number})` + fileExtension
		if (await client.exists(userRoot + uniquePath) === false) {
			return uniquePath
		}
	}
}

/**
 * Checks the existence of duplicated file names in provided array of uploads.
 *
 * @param {Array} uploads The array of uploads to share
 * @return {boolean} Whether array includes duplicates or not
 */
const hasDuplicateUploadNames = function(uploads) {
	const uploadNames = uploads.map(([_index, { file }]) => {
		return getFileNamePrompt(file.newName || file.name)
	})
	const uploadNamesSet = new Set(uploadNames)

	return uploadNames.length !== uploadNamesSet.size
}

/**
 * Process array of upload and returns separated array with unique filenames and duplicates
 *
 * @param {Array} uploads The array of uploads to share
 * @return {object} separated unique and duplicate uploads
 */
function separateDuplicateUploads(uploads) {
	const nameCount = new Set()
	const uniques = []
	const duplicates = []

	// Count the occurrences of each name
	for (const upload of uploads) {
		const name = getFileNamePrompt(upload.at(1).file.newName || upload.at(1).file.name)

		if (nameCount.has(name)) {
			duplicates.push(upload)
		} else {
			uniques.push(upload)
			nameCount.add(name)
		}
	}

	return { uniques, duplicates }
}

export {
	findUniquePath,
	extractFileName,
	getFileExtension,
	getFileNamePrompt,
	hasDuplicateUploadNames,
	separateDuplicateUploads,
}
