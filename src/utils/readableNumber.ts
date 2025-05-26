/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Splits a string into chunks of a given size
 * Samples:
 * stringChop('Hello World', 3) => ['Hel', 'lo ', 'Wor', 'ld']
 * stringChop('Hello World', 5) => ['Hello', ' Worl', 'd']
 *
 * @param str The string to chop
 * @param size Size of the chunks
 * @param fromRight Whether to parse chunks from right side (e.g. a thousand delimiter)
 */
function stringChop(str: string, size: number, fromRight = false): string[] {
	if (size <= 0) {
		return [str]
	}
	const chunks: string[] = []
	if (fromRight) {
		for (let i = str.length; i > 0; i -= size) {
			chunks.unshift(str.slice(Math.max(0, i - size), i))
		}
	} else {
		for (let i = 0; i < str.length; i += size) {
			chunks.push(str.slice(i, i + size))
		}
	}

	return chunks
}

/**
 * Splits a number into chunks of 3 digits (single digit as last is merged into the previous chunk):
 * Samples:
 * 9432670 => 943 2670
 * 94326702 => 943 267 02
 * 943267028 => 943 267 028
 * 9432670284 => 943 267 0284
 *
 * @param number The number to make readable
 * @param fromRight Whether to parse chunks from right side (e.g. a thousand delimiter)
 */
function readableNumber(number: string | number, fromRight = false): string {
	const chunks = stringChop(number.toString(), 3, fromRight)

	const lastChunk = chunks.pop()
	const shouldConcatLastChunk = !lastChunk?.length || lastChunk.length <= 1

	return [chunks.join(' '), lastChunk].join(shouldConcatLastChunk ? '' : ' ')
}

export {
	readableNumber,
	stringChop,
}
