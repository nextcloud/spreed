/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage } from '../types/index.ts'

import { MESSAGE } from '../constants.ts'
import { getFileKeys, hasOnlyFilePlaceholders } from './message.ts'

export type CombinedFileMessage = ChatMessage & {
	/** Ids of all messages combined into this one, in chronological order */
	combinedMessageIds: ChatMessage['id'][]
}

/**
 * Check whether the message is a plain file share, which can be combined with its neighbours
 *
 * @param message message to check
 */
function isCombinableFileMessage(message: ChatMessage): boolean {
	// Only regular messages (excludes deleted messages, voice messages and call recordings)
	if (message.systemMessage !== '' || message.messageType !== MESSAGE.TYPE.COMMENT) {
		return false
	}

	// TODO threads?
	// TODO unified uploader?
	// Temporary messages, which are not sent yet (or failed to be sent), are shown separately
	if (message.timestamp === 0 || (message as ChatMessage & { sendingFailure?: string }).sendingFailure) {
		return false
	}

	const parameters = Object(message.messageParameters) as ChatMessage['messageParameters']
	const keys = Object.keys(parameters)

	// Exactly one file and no other rich object (poll, location, deck card, …)
	if (keys.filter((key) => key.startsWith('file')).length !== 1
		|| keys.some((key) => key.startsWith('object'))) {
		return false
	}

	const file = parameters.file
	if (!file || file.type !== 'file' || !file.mimetype) {
		return false
	}

	// Contact cards and audio players are rendered as their own widgets
	return file.mimetype !== 'text/vcard' && !file.mimetype.startsWith('audio/')
}

/**
 * Check whether two file shares belong to each other: they are replies to the same message
 * (or no replies at all) and they were shared together in a single context
 *
 * @param message1 the new message
 * @param message2 the previous message
 */
function canBeCombinedWith(message1: ChatMessage, message2: ChatMessage): boolean {
	return message1.parent?.id === message2.parent?.id
		// FIXME the timestamp is not a reliable indicator here, should instead
		// create referenceId differently (e.g. as `${SHA(uploadId) + SHA(Math.random())}`)
		// and base splitting on this (should be aligned with mobile clients as well)
		&& message1.timestamp - message2.timestamp <= 30
}

/**
 * Create a single message out of several file shares.
 * The combined message is based on the last message of the group, so that the timestamp,
 * the reading state, the reactions and the message actions refer to an existing message
 * (reactions of the other messages of the group are not shown)
 *
 * @param messages array of grouped file share messages, in chronological order
 */
export function createCombinedFileMessage(messages: ChatMessage[]): CombinedFileMessage {
	const lastMessage = messages.at(-1)!
	const combinedMessage = { ...lastMessage } as CombinedFileMessage

	// Keep parameters of the caption (mentions, for example), but re-index the files
	combinedMessage.messageParameters = Object.fromEntries(Object.entries(Object(lastMessage.messageParameters) as ChatMessage['messageParameters'])
		.filter(([key]) => !key.startsWith('file')))
	messages.forEach((message, index) => {
		combinedMessage.messageParameters[`file-${index + 1}`] = {
			...message.messageParameters.file,
			// @ts-expect-error: 'referenceId' does not exist in type RichObjectParameter,
			referenceId: message.referenceId,
		}
	})

	// Same shape as a single file share: either a caption or the combined placeholders,
	// file placeholders are rendered from the message parameters
	const filePlaceholdersString = getFileKeys(combinedMessage).map((key) => `{${key}}`).join(' ')
	combinedMessage.message = hasOnlyFilePlaceholders(lastMessage.message) ? filePlaceholdersString : lastMessage.message.trim()

	combinedMessage.combinedMessageIds = messages.map((message) => message.id)

	return combinedMessage
}

/**
 * Replace consecutive file shares in the list of messages of one author with combined messages.
 * A message, which is not a plain file share, a reply to another message or a part of another
 * upload, interrupts the combination. A file share with a caption ends it (and provides the caption)
 *
 * @param messages array of messages of one author, in chronological order
 */
export function combineFileMessages(messages: ChatMessage[]): (ChatMessage | CombinedFileMessage)[] {
	const results: (ChatMessage | CombinedFileMessage)[] = []
	let group: ChatMessage[] = []

	const flushGroup = () => {
		if (group.length > 1) {
			results.push(createCombinedFileMessage(group))
		} else if (group.length === 1) {
			results.push(group[0])
		}
		group = []
	}

	for (const message of messages) {
		if (!isCombinableFileMessage(message)) {
			flushGroup()
			results.push(message)
			continue
		}

		// Replies to different messages or files of another upload are not combined with each other
		if (group.length > 0 && !canBeCombinedWith(message, group.at(-1)!)) {
			flushGroup()
		}

		group.push(message)

		if (!hasOnlyFilePlaceholders(message.message)) {
			flushGroup()
		}
	}
	flushGroup()

	return results
}
