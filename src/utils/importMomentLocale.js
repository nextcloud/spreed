/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
 *
 * @author Grigorii Shartsev <me@shgk.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

// TODO: move to @nextcloud/l10n or @nextcloud/moment ?

import { getLocale } from '@nextcloud/l10n'

/**
 * Try to import moment locale
 *
 * @param {string} locale - locale key, for example, fr-fr, de-de, en
 * @return {Promise<boolean>} was import success
 */
async function tryImportMomentLocale(locale) {
	try {
		await import(
			/* webpackInclude: /\.js$/ */
			/* webpackChunkName: 'moment-locales/[request]' */
			`moment/locale/${locale}`
		)
		console.debug(`Moment locale "${locale}" has been loaded`)
		return true
	} catch (error) {
		console.debug(`Moment locale "${locale}" was not found`)
		return false
	}
}

/**
 * Load current locale for moment.js
 * EN is used as a fallback.
 *
 * @return {Promise}
 */
export async function importMomentLocale() {
	// Default locale, e.g. fr-fr
	const locale = getLocale().replace('_', '-').toLowerCase()
	if (await tryImportMomentLocale(locale)) {
		return
	}

	// Fallback, e.g. de
	const alternativeLocale = locale.split('-')[0]
	if (alternativeLocale !== locale && await tryImportMomentLocale(alternativeLocale)) {
		return
	}

	// Fallback to EN
	await tryImportMomentLocale('en')
}
