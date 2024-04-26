/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getBaseUrl } from '@nextcloud/router'

import type { ChatMessage, Mention } from '../types'

/**
 * Parse message text to return proper formatting for mentions
 *
 * @param text The string to parse
 * @param parameters The parameters that contain the mentions
 */
function parseMentions(text: string, parameters: ChatMessage['messageParameters']): string {
	for (const key of Object.keys(parameters).filter(key => key.startsWith('mention'))) {
		const value: Mention = parameters[key]
		let mention = ''

		if (key.startsWith('mention-call') && value.type === 'call') {
			mention = '@all'
		} else if (key.startsWith('mention-federated-user') && value.type === 'user') {
			const server = value?.server ?? getBaseUrl().replace('https://', '')
			mention = `@"federated_user/${value.id}@${server}"`
		} else if (key.startsWith('mention-group') && value.type === 'user-group') {
			mention = `@"group/${value.id}"`
		} else if (key.startsWith('mention-user') && value.type === 'user') {
			mention = value.id.includes(' ') ? `@"${value.id}"` : `@${value.id}`
		}

		if (mention) {
			text = text.replace(new RegExp(`{${key}}`, 'g'), mention)
		}
	}
	return text
}

/**
 * Parse special symbols in text like &amp; &lt; &gt; &sect;
 * FIXME upstream: https://github.com/nextcloud-libraries/nextcloud-vue/issues/4492
 *
 * @param text The string to parse
 */
function parseSpecialSymbols(text: string): string {
	const temp = document.createElement('textarea')
	temp.innerHTML = text.replace(/&/gmi, '&amp;')
	text = temp.value.replace(/&amp;/gmi, '&').replace(/&lt;/gmi, '<')
		.replace(/&gt;/gmi, '>').replace(/&sect;/gmi, '§')
		.replace(/^\s+|\s+$/g, '') // remove trailing and leading whitespaces
		.replace(/\r\n|\n|\r/gm, '\n') // remove line breaks
	return text
}

export {
	parseSpecialSymbols,
	parseMentions,
}
