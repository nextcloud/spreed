/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { WebDAVClient } from 'webdav'
import type { UploadEntry, UploadFile } from '../types/index.ts'

const extensionRegex = /\.[0-9a-z]+$/i
const suffixRegex = / \(\d+\)$/

/**
 * Returns the file extension for the given path
 *
 * @param path file path
 * @return file extension including the dot
 */
export function getFileExtension(path: string): string {
	return path.match(extensionRegex)?.[0] ?? ''
}

/**
 * Returns the file suffix for the given path
 *
 * @param path file path
 * @return file suffix excluding the parenthesis (2, 3, etc.) or 1 if no suffix is found
 */
export function getFileSuffix(path: string): number {
	return parseInt(
		path.replace(extensionRegex, '').match(suffixRegex)?.[0]?.match(/\d+/)?.[0] ?? '1',
		10,
	)
}

/**
 * Returns the file name without extension and digit suffix
 *
 * @param path file path
 * @return extracted file name
 */
export function extractFileName(path: string): string {
	return path
	// If there is a file extension, remove it from the path string
		.replace(extensionRegex, '')
	// If a filename ends with suffix ` (n)`, remove it from the path string
		.replace(suffixRegex, '')
}

/**
 * Returns the file name prompt for the given path
 *
 * @param path file path
 * @return file name prompt
 */
export function getFileNamePrompt(path: string): string {
	return extractFileName(path) + getFileExtension(path)
}

/**
 * Checks the existence of a path in a folder and if a match is found, returns
 * a unique path for that folder.
 *
 * @param client WebDAV client object
 * @param userRoot user root path
 * @param path The path whose existence in the destination is to be checked
 * @param knownSuffix The suffix to start looking from
 * @return The unique path and suffix
 */
export async function findUniquePath(client: WebDAVClient, userRoot: string, path: string, knownSuffix: number): Promise<{ uniquePath: string, suffix: number }> {
	// Return the input path if it doesn't exist in the destination folder
	if (!knownSuffix && await client.exists(userRoot + path) === false) {
		return { uniquePath: path, suffix: getFileSuffix(path) }
	}

	const fileExtension = getFileExtension(path)
	const fileName = extractFileName(path)

	// Loop until a unique path is found
	let suffix = knownSuffix || getFileSuffix(path)
	while (true) {
		suffix++
		const uniquePath = fileName + ` (${suffix})` + fileExtension
		if (await client.exists(userRoot + uniquePath) === false) {
			return { uniquePath, suffix }
		}
	}
}

/**
 * Checks the existence of duplicated file names in provided array of uploads.
 *
 * @param uploads The array of uploads to share
 * @return Whether array includes duplicates or not
 */
export function hasDuplicateUploadNames(uploads: UploadEntry[]): boolean {
	const uploadNames = uploads.map(([_index, { file }]) => {
		return getFileNamePrompt(file.newName || file.name)
	})
	const uploadNamesSet = new Set(uploadNames)

	return uploadNames.length !== uploadNamesSet.size
}

/**
 * Process array of upload and returns separated array with unique filenames and duplicates
 *
 * @param uploads The array of uploads to share
 * @return separated unique and duplicate uploads
 */
export function separateDuplicateUploads(uploads: UploadEntry[]): { uniques: UploadEntry[], duplicates: UploadEntry[] } {
	const nameCount = new Set()
	const uniques = []
	const duplicates = []

	// Count the occurrences of each name
	for (const upload of uploads) {
		const name = getFileNamePrompt(upload[1].file.newName || upload[1].file.name)

		if (nameCount.has(name)) {
			duplicates.push(upload)
		} else {
			uniques.push(upload)
			nameCount.add(name)
		}
	}

	return { uniques, duplicates }
}
