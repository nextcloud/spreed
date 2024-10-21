/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type VoteAccumulator = { rounded: number[], wholes: number[], remainders: number[] }

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
export function countPollVotes(votes: number[], total: number) {
	if (!total) {
		return votes
	}

	const { rounded, wholes, remainders } = votes.reduce((acc: VoteAccumulator, vote, idx) => {
		const quota = vote / total * 100
		acc.rounded[idx] = Math.round(quota)
		acc.wholes[idx] = Math.floor(quota)
		acc.remainders[idx] = Math.round((quota - Math.floor(quota)) * 1000)
		return acc
	}, { rounded: [], wholes: [], remainders: [] })

	// Check if simple round gives 100%
	if (rounded.reduce((acc, value) => acc + value) === 100) {
		return rounded
	}

	// Increase values by largest remainder method if difference allows
	for (let i = 100 - wholes.reduce((acc, value) => acc + value); i > 0;) {
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
