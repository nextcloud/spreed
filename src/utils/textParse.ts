/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { decodeHTML } from 'entities'

import { getBaseUrl } from '@nextcloud/router'

import { MENTION } from '../constants.ts'
import type { ChatMessage, Mention } from '../types/index.ts'

/**
 * Parse message text to return proper formatting for mentions
 *
 * @param text The string to parse
 * @param parameters The parameters that contain the mentions
 */
function parseMentions(text: string, parameters: ChatMessage['messageParameters']): string {
	for (const key of Object.keys(Object(parameters)).filter(key => key.startsWith('mention'))) {
		const value: Mention = parameters[key] as Mention
		let mention = ''

		if (value['mention-id']) {
			mention = `@"${value['mention-id']}"`
		} else if (key.startsWith('mention-call') && value.type === MENTION.TYPE.CALL) {
			mention = '@all'
		} else if (key.startsWith('mention-federated-user')
			&& [MENTION.TYPE.USER, MENTION.TYPE.FEDERATED_USER].includes(value.type)) {
			mention = `@"federated_user/${value.id}@${(value?.server ?? getBaseUrl()).replace('https://', '')}"`
		} else if (key.startsWith('mention-group')
			&& [MENTION.TYPE.USERGROUP, MENTION.TYPE.GROUP].includes(value.type)) {
			mention = `@"group/${value.id}"`
		} else if (key.startsWith('mention-team')
			&& [MENTION.TYPE.CIRCLE, MENTION.TYPE.TEAM].includes(value.type)) {
			mention = `@"team/${value.id}"`
		} else if (key.startsWith('mention-guest') && value.type === MENTION.TYPE.GUEST) {
			// id and mention-id are both prefixed with "guest/"
			mention = `@"${value.id}"`
		} else if (key.startsWith('mention-email') && value.type === MENTION.TYPE.EMAIL) {
			mention = `@"email/${value.id}"`
		} else if (key.startsWith('mention-user') && value.type === MENTION.TYPE.USER) {
			mention = `@"${value.id}"`
		}

		if (mention) {
			// It's server-side value, we can skip the security check
			// nosemgrep
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
	return decodeHTML(text) // decode HTML entities
		.replace(/^\s+|\s+$/g, '') // remove trailing and leading whitespaces
		.replace(/\r\n|\n|\r/gm, '\n') // remove line breaks
}

export {
	parseSpecialSymbols,
	parseMentions,
}
