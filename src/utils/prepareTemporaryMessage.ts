/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage, File } from '../types/index.ts'

import Hex from 'crypto-js/enc-hex.js'
import SHA256 from 'crypto-js/sha256.js'
import { MESSAGE } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'

export type PrepareTemporaryMessagePayload = Pick<ChatMessage,
	| 'message'
	| 'token'
	| 'actorId'
	| 'actorType'
	| 'actorDisplayName'
	| 'silent'
> & {
	uploadId: string
	index: number
	file: File & { newName?: string }
	localUrl: string
	messageType?: typeof MESSAGE.TYPE['VOICE_MESSAGE' | 'COMMENT']
	parent: Omit<ChatMessage, 'parent'>
}

/**
 * Creates a temporary message ready to be posted, based
 * on the message to be replied and the current actor
 *
 * @param payload the wrapping object;
 * @param payload.message message string;
 * @param payload.token conversation token;
 * @param payload.uploadId upload id;
 * @param payload.index index;
 * @param payload.file file to upload;
 * @param payload.localUrl local URL of file to upload;
 * @param payload.messageType specify when the temporary file is a voice message
 * @param payload.actorId actor id
 * @param payload.actorType actor type
 * @param payload.actorDisplayName actor displayed name
 * @param [payload.parent] parent message
 */
export function prepareTemporaryMessage({
	message,
	token,
	uploadId,
	index,
	file,
	localUrl,
	messageType = MESSAGE.TYPE.COMMENT,
	actorId,
	actorType,
	actorDisplayName,
	parent,
	silent = false,
}: PrepareTemporaryMessagePayload): ChatMessage {
	const date = new Date()
	let tempId = 'temp-' + date.getTime()
	const messageParameters: ChatMessage['messageParameters'] = {}
	if (file) {
		tempId += '-' + uploadId + '-' + Math.random()
		messageParameters.file = {
			type: 'file',
			// @ts-expect-error: 'file' does not exist in type RichObjectParameter
			file,
			mimetype: file.type,
			id: tempId,
			name: file.newName || file.name,
			// index, will be the id from now on
			uploadId,
			localUrl,
			index,
		}
	}

	return {
		// @ts-expect-error: type 'string' is not assignable to type 'number'
		id: tempId,
		token,
		timestamp: 0,
		expirationTimestamp: 0,
		systemMessage: '',
		markdown: hasTalkFeature(token, 'markdown-messages'),
		messageType,
		message,
		messageParameters,
		parent,
		isReplyable: false,
		reactions: {},
		referenceId: Hex.stringify(SHA256(tempId)),
		actorId,
		actorType,
		actorDisplayName,
		silent,
	}
}
