/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Component } from 'vue'
import type { ChatMessage } from '../../../../types/index.ts'

import { defineAsyncComponent } from 'vue'
import ContactCard from './MessagePart/ContactCard.vue'
import DeckCard from './MessagePart/DeckCard.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import MentionChip from './MessagePart/MentionChip.vue'
import PollCard from './MessagePart/PollCard.vue'
import { MENTION, SHARED_ITEM } from '../../../../constants.ts'
import { isFilePreviewParameter } from '../../../../utils/message.ts'

const LocationCard = defineAsyncComponent(() => import('./MessagePart/LocationCard.vue'))

const MENTION_TYPES = new Set<string>(Object.values(MENTION.TYPE))

type RichParameter = {
	component: Component
	props: Record<string, unknown>
}

export type RichParameters = Record<string, RichParameter>

type MessageParameter = ChatMessage['messageParameters'][string]

/**
 * Maps a message parameter to a component rendered by NcRichText
 *
 * @param message Chat message
 * @param key parameter key
 * @param parameter message parameter
 * @param mentionOnly render non-mention parameters as plain text
 */
function getRichParameter(message: ChatMessage, key: string, parameter: MessageParameter, mentionOnly: boolean): RichParameter | null {
	if (MENTION_TYPES.has(parameter.type)) {
		return { component: MentionChip, props: { ...parameter, token: message.token } }
	}
	if (mentionOnly) {
		return { component: DefaultParameter, props: parameter }
	}

	if (isFilePreviewParameter(key, parameter)) {
		// File previews are rendered by FilePreviewsWrapper
		return null
	}
	if (parameter.type === SHARED_ITEM.OBJECT_TYPE.DECK_CARD) {
		return { component: DeckCard, props: parameter }
	}
	if (parameter.type === SHARED_ITEM.OBJECT_TYPE.LOCATION) {
		return { component: LocationCard, props: parameter }
	}
	if (parameter.type === SHARED_ITEM.OBJECT_TYPE.POLL) {
		return { component: PollCard, props: { ...parameter, token: message.token } }
	}
	if (parameter.mimetype === 'text/vcard') {
		return { component: ContactCard, props: parameter }
	}
	return { component: DefaultParameter, props: parameter }
}

/**
 * Maps message parameters to components rendered by NcRichText
 *
 * @param message Chat message
 * @param options options
 * @param options.mentionOnly render only mentions as chips, other parameters as plain text (e.g. for system messages)
 */
export function getRichParameters(message: ChatMessage, { mentionOnly = false }: { mentionOnly?: boolean } = {}): RichParameters {
	const richParameters: RichParameters = {}

	for (const [key, parameter] of Object.entries(message.messageParameters)) {
		const richParameter = getRichParameter(message, key, parameter, mentionOnly)
		if (richParameter) {
			richParameters[key] = richParameter
		}
	}

	return richParameters
}
