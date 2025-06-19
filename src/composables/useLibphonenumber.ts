/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useAsyncInit } from './useAsyncInit.ts'

/**
 * Dynamically import libphonenumber-js module
 */
async function loadLibphonenumber() {
	// Destructure immediately to help bundlers to tree-shake unused parts
	const { parsePhoneNumberFromString, validatePhoneNumberLength } = await import('libphonenumber-js')
	return {
		parsePhoneNumberFromString,
		validatePhoneNumberLength,
	}
}

/**
 * Use libphonenumber-js asynchronously loaded module
 */
export function useLibphonenumber() {
	const {
		isReady: isLibphonenumberReady,
		result: libphonenumber,
	} = useAsyncInit(loadLibphonenumber, true)

	return {
		isLibphonenumberReady,
		libphonenumber,
	}
}
