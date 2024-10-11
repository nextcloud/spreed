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

	for (let i = 0; i < Math.max(versionMap.length, requirementMap.length); i++) {
		if ((versionMap[i] ?? 0) !== (requirementMap[i] ?? 0)) {
			return (versionMap[i] ?? 0) > (requirementMap[i] ?? 0)
		}
	}
	return true
}

export {
	satisfyVersion,
}
