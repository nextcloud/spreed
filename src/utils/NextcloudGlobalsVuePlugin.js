/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { n, t } from '@nextcloud/l10n'

export const NextcloudGlobalsVuePlugin = (app) => {
	app.config.globalProperties.t = t
	app.config.globalProperties.n = n
	app.config.globalProperties.OC = window.OC
	app.config.globalProperties.OCA = window.OCA
	app.config.globalProperties.OCP = window.OCP
}
