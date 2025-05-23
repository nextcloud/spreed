/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	return path.match(extensionRegex)?.[0] ?? ''
}

/**
 * Returns the file suffix for the given path
 *
 * @param {string} path path
 * @return {number} file suffix excluding the parenthesis
 */
const getFileSuffix = function(path) {
	return parseInt(path.replace(extensionRegex, '')
		.match(suffixRegex)?.[0]?.match(/\d+/)?.[0] ?? 1)
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
 * @param {number} knownSuffix The suffix to start looking from
 * @return {object} The unique path and suffix
 */
const findUniquePath = async function(client, userRoot, path, knownSuffix) {
	// Return the input path if it doesn't exist in the destination folder
	if (!knownSuffix && await client.exists(userRoot + path) === false) {
		return { uniquePath: path, suffix: getFileSuffix(path) }
	}

	const fileExtension = getFileExtension(path)
	const fileName = extractFileName(path)

	// Loop until a unique path is found
	for (let suffix = knownSuffix + 1 || getFileSuffix(path) + 1; true; suffix++) {
		const uniquePath = fileName + ` (${suffix})` + fileExtension
		if (await client.exists(userRoot + uniquePath) === false) {
			return { uniquePath, suffix }
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
	extractFileName,
	findUniquePath,
	getFileExtension,
	getFileNamePrompt,
	getFileSuffix,
	hasDuplicateUploadNames,
	separateDuplicateUploads,
}
