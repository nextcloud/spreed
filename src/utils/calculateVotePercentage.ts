/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Finds indexes of largest remainders to distribute quota
 * @param array array of numbers to compare
 */
function getLargestIndexes(array: number[]) {
	let maxValue = 0
	const maxIndexes: number[] = []

	for (let i = 0; i < array.length; i++) {
		if (array[i] > maxValue) {
			maxValue = array[i]
			maxIndexes.length = 0
			maxIndexes.push(i)
		} else if (array[i] === maxValue) {
			maxIndexes.push(i)
		}
	}

	return maxIndexes
}

/**
 * Provide percentage distribution closest to 100 by method of largest remainder
 * @param votes array of given votes
 * @param total amount of votes
 */
export function calculateVotePercentage(votes: number[], total: number) {
	if (!total) {
		return votes
	}

	const rounded: number[] = []
	const wholes: number[] = []
	const remainders: number[] = []
	let sumRounded = 0
	let sumWholes = 0

	for (const i in votes) {
		const quota = votes[i] / total * 100
		rounded.push(Math.round(quota))
		wholes.push(Math.floor(quota))
		remainders.push(Math.round((quota % 1) * 1000))
		sumRounded += rounded[i]
		sumWholes += wholes[i]
	}

	// Check if simple round gives 100%
	if (sumRounded === 100) {
		return rounded
	}

	// Increase values by largest remainder method if difference allows
	for (let i = 100 - sumWholes; i > 0;) {
		const largest = getLargestIndexes(remainders)
		if (largest.length > i) {
			return wholes
		}

		for (const idx of largest) {
			wholes[idx]++
			remainders[idx] = 0
			i--
		}
	}

	return wholes
}
