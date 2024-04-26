/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Copied from https://www.w3resource.com/javascript-exercises/javascript-string-exercise-17.php
 *
 * @param str The string to chop
 * @param size Size of the chunks
 */
function stringChop(str: string, size: number): string[] {
	if (size <= 0) {
		return [str]
	}
	return str.match(new RegExp('.{1,' + size + '}', 'g')) ?? [str]
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
 */
function readableNumber(number: string | number): string {
	const chunks = stringChop(number.toString(), 3)

	const lastChunk = chunks.pop()
	const shouldConcatLastChunk = !lastChunk?.length || lastChunk.length <= 1

	return [chunks.join(' '), lastChunk].join(shouldConcatLastChunk ? '' : ' ')
}

export {
	readableNumber,
	stringChop,
}
