/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { readableNumber } from './readableNumber.ts'

const timersPool: Record<string, number> = {}

/**
 *
 */
function getReadable(time: number) {
	if (isNaN(time)) {
		return '--.-- ms'
	}
	try {
		const [int, float] = time.toFixed(2).split('.')
		return `${readableNumber(int, true)}.${float} ms`
	} catch (e) {
		console.error(e)
		return '--.-- ms'
	}
}

export const debugTimer = {
	start: (name: string) => {
		timersPool[name] = performance.now()
	},
	end: (name: string, payload: unknown) => {
		console.debug(`[DEBUG] spreed: ${name} | ${getReadable(performance.now() - timersPool[name])}`, payload)
		delete timersPool[name]
	},
	tick: (name: string, payload: unknown) => {
		console.debug(`[DEBUG] spreed: ${name} | ${getReadable(performance.now() - timersPool[name])}`, payload)
		timersPool[name] = performance.now()
	},
}
