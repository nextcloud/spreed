/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Checks if given version satisfy requirements (newer than)
 * Versions are expected to have format like '29.1.4.3'
 * @param version given version
 * @param requirement version to compare against
 */
function satisfyVersion(version: string, requirement: string): boolean {
	const versionMap = version.split('.').map(Number)
	const requirementMap = requirement.split('.').map(Number)

	for (let i = 0; i < requirementMap.length; i++) {
		if (versionMap[i] !== requirementMap[i]) {
			return versionMap[i] > requirementMap[i]
		}
	}
	return true
}

export {
	satisfyVersion,
}
