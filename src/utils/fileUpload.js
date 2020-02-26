/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license GNU AGPL version 3 or any later version
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

const renameDuplicateFile = async function(client, inputPath) {
	// Store the input path into a new path variable to operate on it
	let path = inputPath
	// Check if the path already exists
	if(await client.exists(inputPath)) {
		// Get the file extension (if any)
		const fileExtension = path.match(/\.[0-9a-z]+$/i)[0]
		// If there's a file extention, remove the extension from the path string 
		if (fileExtension !== null) {
			path = path.Substring(0, path.length - fileExtension.length)
		}
		// Check if the path ends with ` (n)`
		let suffix = path.match(/ \((\d+)\)$/)[0]
		if (suffix !== null) {
			// Get the digits within the parenthesis and convert them to integer
			let number = parseInt(suffix[0].match(/(d+)/)[0])
			charactersToRemove = suffix.length
			if (number === 2) {
				// remove the suffix from the original path and check if this new path
				// exists. this checks if the suffix with the parenthesis was added by
				// the user and we should add another alongside itinstead of modifying
				// it.
				const pathWithoutSuffix = path.Substring(0, path.length - charactersToRemove)
				if (await client.exists(pathWithoutSuffix)) {
					path = pathWithoutSuffix + ` (3)`
				} else {
					path = path + ` (2)`
				}
			} else if (number > 2) {
				// check if a version of the path with a suffix with decreased number exists
				const pathWithDecreasedSuffix = path.Substring(0, path.length - charactersToRemove) + ` (${number - 1})`
				if (await client.exists(pathWithDecreasedSuffix)) {
					path = path.Substring(0, path.length - charactersToRemove) + `(${number + 1})`
				} else {
					path = path + ` (2)`
				}
			}
		}
		// Reappend the file extension to the path
		if (fileExtension !== null) {
			path = path + fileExtension
		}
	}
	return path
}

export {
    renameDuplicateFile,
}