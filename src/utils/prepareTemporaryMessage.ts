/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage, File } from '../types/index.ts'

import Hex from 'crypto-js/enc-hex.js'
import SHA256 from 'crypto-js/sha256.js'
import { MESSAGE } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { convertToUnix } from './formattedTime.ts'

export type RawTemporaryMessagePayload = Pick<ChatMessage, | 'message'
	| 'token'
	| 'silent'> & Partial<Pick<ChatMessage, | 'actorId'
	| 'actorType'
	| 'actorDisplayName'
	| 'threadId'
	| 'isThread'
	| 'threadTitle'
	| 'threadReplies'
	| 'parent'>> & {
		uploadId?: string
		index?: string
		file?: File
		localUrl?: string
		messageType?: typeof MESSAGE.TYPE['VOICE_MESSAGE' | 'COMMENT']
	}

export type PrepareTemporaryMessagePayload = Pick<ChatMessage, | 'message'
	| 'token'
	| 'actorId'
	| 'actorType'
	| 'actorDisplayName'
	| 'silent'
	| 'threadId'
	| 'isThread'
	| 'threadTitle'
	| 'threadReplies'
	| 'parent'> & {
		uploadId?: string
		index?: string
		file?: File
		localUrl?: string
		messageType?: typeof MESSAGE.TYPE['VOICE_MESSAGE' | 'COMMENT']
	}

export type TempChatMessageWithFile = Omit<ChatMessage, 'messageParameters'> & {
	messageParameters: ChatMessage['messageParameters'] & {
		file: ChatMessage['messageParameters'][string] & {
			file: File
			uploadId: string
			index: number
			localUrl?: string
		}
	}
}

/**
 * Creates a temporary message ready to be posted, based
 * on the message to be replied and the current actor
 *
 * @param payload the wrapping object;
 * @param payload.message message string;
 * @param payload.token conversation token;
 * @param payload.uploadId upload id;
 * @param payload.index index of file. must be provided with file and uploadId;
 * @param payload.file file to upload;
 * @param payload.localUrl local URL of file to upload;
 * @param payload.messageType specify when the temporary file is a voice message
 * @param payload.actorId actor id
 * @param payload.actorType actor type
 * @param payload.actorDisplayName actor displayed name
 * @param [payload.parent] parent message
 * @param payload.silent
 * @param payload.threadId
 * @param payload.isThread
 * @param payload.threadTitle
 * @param payload.threadReplies
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
	threadId,
	threadTitle,
	threadReplies,
	isThread,
}: PrepareTemporaryMessagePayload): ChatMessage | TempChatMessageWithFile {
	const date = new Date()
	let tempId: string = 'temp-'
	let referenceId: string
	const messageParameters: ChatMessage['messageParameters'] = {}
	if (file) {
		if (!index || !uploadId) {
			throw new Error('[prepareTemporaryMessage]: index/uploadId is required for file messages')
		}
		const appendedIndex = index.split('_').pop()!.padStart(3, '0')
		tempId += uploadId + '-' + appendedIndex

		/**
		 * Construct file share message referenceId in the following format:
		 * /[a-f0-9]{60}-[0-9]{3}/, where:
		 * /[a-f0-9]{60}/ - uploadId hashed with SHA-256 algorithm
		 * /-/            - mandatory delimiter
		 * /[0-9]{3}/     - order of file in given upload (natural integers, padded with zeroes)
		 */
		referenceId = Hex.stringify(SHA256(uploadId)).slice(0, 60) + '-' + appendedIndex

		messageParameters.file = {
			type: 'file',
			// @ts-expect-error: 'file' does not exist in type RichObjectParameter
			file,
			mimetype: file.type,
			id: tempId,
			name: file.name,
			// index, will be the id from now on
			uploadId,
			localUrl,
			index,
		}
	} else {
		tempId += date.getTime()
		referenceId = Hex.stringify(SHA256(tempId))
	}

	if (parent && 'token' in parent && parent.token !== token) {
		parent = {
			...parent,
			metaData: {
				...('metaData' in parent ? parent.metaData : {}),
				replyToConversationToken: parent.token,
			},
		}
	}

	return {
		// @ts-expect-error: type 'string' is not assignable to type 'number'
		id: tempId,
		token,
		timestamp: convertToUnix(date),
		expirationTimestamp: 0,
		systemMessage: '',
		markdown: hasTalkFeature(token, 'markdown-messages'),
		messageType,
		message,
		messageParameters,
		parent,
		isReplyable: false,
		reactions: {},
		referenceId,
		actorId,
		actorType,
		actorDisplayName,
		silent,
		threadId,
		threadTitle,
		threadReplies,
		isThread,
	}
}
