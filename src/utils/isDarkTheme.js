/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Check if dark theme is used
 *
 * @return {boolean}
 */
function checkIfDarkTheme() {
	// Nextcloud uses --background-invert-if-dark for dark theme filters in CSS
	// Values:
	// - 'invert(100%)' for dark theme
	// - 'no' for light theme
	return window.getComputedStyle(document.body).getPropertyValue('--background-invert-if-dark') === 'invert(100%)'
}

/**
 * Is Dark Theme enabled
 * We do not support dark/light theme update without reload the page, so the value can be computed once
 *
 * @type {boolean}
 */
export const isDarkTheme = checkIfDarkTheme()
