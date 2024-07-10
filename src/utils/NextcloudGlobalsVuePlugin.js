/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const NextcloudGlobalsVuePlugin = (app) => {
	app.config.globalProperties.OC = window.OC
	app.config.globalProperties.OCA = window.OCA
	app.config.globalProperties.OCP = window.OCP
}
