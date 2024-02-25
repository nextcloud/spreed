/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <grigorii.shartsev@nextcloud.com>
 *
 * @author Grigorii Shartsev <grigorii.shartsev@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
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

import { translate, translatePlural } from '@nextcloud/l10n'

declare global {
	// @nextcloud/webpack-vue-config build globals
	const appName: string
	const appVersion: string

	/**
	 * Build constant to divide build for web app and desktop client
	 */
	const IS_DESKTOP: false

	let __webpack_nonce__: ReturnType<typeof btoa>
	let __webpack_public_path__: string

	const t: typeof translate
	const n: typeof translatePlural
}

export {}
