/**
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Copied from https://www.w3resource.com/javascript-exercises/javascript-string-exercise-17.php
 *
 * @param {string} str The string to chop
 * @param {number} size Size of the chunks
 * @return {string[]}
 */
function stringChop(str, size) {
	str = String(str)
	size = ~~size
	return size > 0 ? str.match(new RegExp('.{1,' + size + '}', 'g')) : [str]
}

/**
 * Splits a number into chunks of 3 digits (single digit as last is merged into the previous chunk):
 * Samples:
 * 9432670 => 943 2670
 * 94326702 => 943 267 02
 * 943267028 => 943 267 028
 * 9432670284 => 943 267 0284
 *
 * @param {string} number The number to make readable
 * @return {string}
 */
function readableNumber(number) {
	const chunks = stringChop(number, 3)
	const lastChunk = chunks.pop()
	if (lastChunk.length === 1) {
		return chunks.join(' ') + lastChunk
	}
	return chunks.join(' ') + ' ' + lastChunk
}

export {
	readableNumber,
}
