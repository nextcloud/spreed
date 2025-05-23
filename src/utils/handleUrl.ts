/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { UrlOptions } from '@nextcloud/router'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl, getBaseUrl } from '@nextcloud/router'

/**
 * Generate a full absolute link with @nextcloud/router.generateUrl
 *
 * @param url - path
 * @param params - parameters to be replaced into the address
 * @param options - options for the parameter replacement
 */
export function generateAbsoluteUrl(url: string, params?: object, options: UrlOptions = {}): string {
	// TODO: add this function to @nextcloud/router?
	return generateUrl(url, params, {
		baseURL: getBaseUrl(),
		...options,
	})
}

/**
 * Generate a full link to conversation
 *
 * @param token - Conversation token
 * @param [messageId] - messageId for message in conversation link
 */
export function generateFullConversationLink(token: string, messageId?: string): string {
	return messageId !== undefined
		? generateAbsoluteUrl('/call/{token}#message_{messageId}', { token, messageId })
		: generateAbsoluteUrl('/call/{token}', { token })
}

/**
 * Try to copy conversation link to a clipboard and display the result with dialogs
 *
 * @param token - conversation token
 * @param [messageId] - messageId for message in conversation link
 */
export async function copyConversationLinkToClipboard(token: string, messageId?: string) {
	try {
		await navigator.clipboard.writeText(generateFullConversationLink(token, messageId))
		showSuccess(t('spreed', 'Conversation link copied to clipboard'))
	} catch (error) {
		showError(t('spreed', 'The link could not be copied'))
	}
}
