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

/**
 * Returns the file extension for the given path
 *
 * @param {string} path path
 * @return {string} file extension including the dot
 */
const getFileExtension = function(path) {
	return path.match(/\.[0-9a-z]+$/i) ? path.match(/\.[0-9a-z]+$/i)[0] : ''
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
	// Get the file extension (if any)
	const fileExtension = getFileExtension(path)
	// If there's a file extention, remove it from the path string
	if (fileExtension !== '') {
		path = path.substring(0, path.length - fileExtension.length)
	}
	// Check if the path ends with ` (n)`
	const suffix = path.match(/ \((\d+)\)$/) ? path.match(/ \((\d+)\)$/) : ''
	// Initialise a pathwithout suffix variable
	let pathWithoutSuffix = path
	if (suffix !== '') {
		// remove the suffix if any
		pathWithoutSuffix = path.substring(0, path.length - suffix.length)
	}
	// Loop until a unique path is found
	for (let number = 2; true; number++) {
		const uniquePath = pathWithoutSuffix + ` (${number})` + (fileExtension)
		if (await client.exists(userRoot + uniquePath) === false) {
			return uniquePath
		}

	}
}

export {
	findUniquePath,
	getFileExtension,
}
