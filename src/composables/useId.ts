/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Generate a random string id for HTML RefIDs
 * @todo Replace with real useId composable from Vue 3.5 after upgrade
 * @return {string} String with 6 characters of digits and letters
 */
export function useId(): string {
	return Math.random().toString(36).slice(2, 9)
}
