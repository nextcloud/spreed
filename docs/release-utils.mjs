/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Release-specific helpers shared by bump-version.mjs and validate-release.mjs:
 * version parsing, bumping and date formatting.
 */

/**
 * Extract the app version from an appinfo/info.xml string.
 *
 * @param {string|null} xml the file contents
 * @return {string} the version, or '' when not found
 */
export function parseInfoVersion(xml) {
	if (!xml) {
		return ''
	}
	const match = xml.match(/<version>([^<]*)<\/version>/)
	return match ? match[1] : ''
}

/**
 * Increment the last numeric component of a version string.
 * e.g. 24.0.0-rc.3 → 24.0.0-rc.4   |   23.0.5 → 23.0.6
 *
 * @param {string} version the version to bump
 * @return {string} the incremented version (unchanged if no trailing number)
 */
export function incrementVersion(version) {
	const match = version.match(/^(.*\.)(\d+)$/)
	if (match) {
		return `${match[1]}${Number(match[2]) + 1}`
	}
	return version
}

/**
 * Today's date as YYYY-MM-DD.
 *
 * @return {string} the ISO date
 */
export function today() {
	return new Date().toISOString().slice(0, 10)
}

/**
 * Today's date as YYYYMMDD.
 *
 * @return {string} the compact date
 */
export function todayCompact() {
	return today().replace(/-/g, '')
}
