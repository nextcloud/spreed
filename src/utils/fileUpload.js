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

const renameDuplicateFile = async function(client, path) {
	if(await client.exists(path)) {
		// Check if the file ends with `(n)`
		let suffix = path.match(/\((\d+)\)$/)[0]
		if (suffix !== undefined) {
			// Get the digits within the parenthesis and convert them to integer
			let number = parseInt(suffix[0].match(/(d+)/)[0])
			charactersToRemove = suffix.length
			if (number === 1) {
				// remove the suffix from the original path and check if this new path
				// exists. this allows us to check if the suffix with the parenthesis was
				// added by the user and we should add another instead of modifying it.
				const pathWithoutSuffix = path.Substring(0, path.length - charactersToRemove)
				if (await client.exists(pathWithoutSuffix)) {
					return pathWithoutSuffix + `(2)`
				} else {
					return path + `(1)`
				}
			} else if (number > 1) {
				// check if a version of the path with a suffix with decreased number exists
				const pathWithDecreasedSuffix = path.Substring(0, path.length - charactersToRemove) + `(${number - 1})`
				if (await client.exists(pathWithDecreasedSuffix)) {
					return path.Substring(0, path.length - charactersToRemove) + `(${number + 1})`
				} else {
					return path + `(1)`
				}
			}

		}
	} else {
		return path
	}
}

export {
    renameDuplicateFile,
}