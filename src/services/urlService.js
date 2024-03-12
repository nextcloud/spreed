/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <grigorii.shartsev@nextcloud.com>
 *
 * @author Grigorii Shartsev <grigorii.shartsev@nextcloud.com>
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

import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

/**
 * Generate a full absolute link with @nextcloud/router.generateUrl
 *
 * @see @nextcloud/router.generateUrl
 * @param {string} url - Path
 * @param {object} [params] parameters to be replaced into the address
 * @param {import('@nextcloud/router').UrlOptions} [options] options for the parameter replacement
 * @param {boolean} options.noRewrite True if you want to force index.php being added
 * @param {boolean} options.escape Set to false if parameters should not be URL encoded (default true)
 * @return {string} Full absolute URL
 */
export function generateAbsoluteUrl(url, params, options) {
	// TODO: add this function to @nextcloud/router?
	const fullPath = generateUrl(url, params, options)
	if (!IS_DESKTOP) {
		return `${window.location.protocol}//${window.location.host}${fullPath}`
	} else {
		// On the Desktop generateUrl creates absolute url by default
		return fullPath
	}
}

/**
 * Generate full link to conversation
 *
 * @param {string} token - Conversation token
 * @param {string} [messageId] - messageId for message in conversation link
 * @return {string} - Absolute URL to conversation
 */
export function generateFullConversationLink(token, messageId) {
	return messageId !== undefined
		? generateAbsoluteUrl('/call/{token}#message_{messageId}', {
			token,
			messageId,
		})
		: generateAbsoluteUrl('/call/{token}', { token })
}

/**
 * Try to copy conversation link to a clipboard and display the result with dialogs
 *
 * @param {string} token - conversation token
 * @param {string} [messageId] - messageId for message in conversation link
 * @return {Promise<void>}
 */
export async function copyConversationLinkToClipboard(token, messageId) {
	try {
		await navigator.clipboard.writeText(generateFullConversationLink(token, messageId))
		showSuccess(t('spreed', 'Conversation link copied to clipboard'))
	} catch (error) {
		showError(t('spreed', 'The link could not be copied'))
	}
}
