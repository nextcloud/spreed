/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ATTENDEE, MESSAGE } from '../../constants.ts'
import { convertToUnix } from '../formattedTime.ts'
import { prepareTemporaryMessage } from '../prepareTemporaryMessage.ts'

describe('prepareTemporaryMessage', () => {
	const TOKEN = 'XXTOKENXX'
	const UPLOAD_ID = String(new Date('2020-01-01T20:00:00').getTime())

	beforeEach(() => {
		vi.useFakeTimers().setSystemTime(new Date('2020-01-01T20:00:00'))
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	const defaultPayload = {
		message: 'message text',
		token: TOKEN,
		actorId: 'actor-id-1',
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		actorDisplayName: 'Actor One',
	}
	const defaultResult = {
		actorId: 'actor-id-1',
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		actorDisplayName: 'Actor One',
		expirationTimestamp: 0,
		id: 'temp-1577908800000',
		isReplyable: false,
		markdown: true,
		message: 'message text',
		messageParameters: {},
		messageType: MESSAGE.TYPE.COMMENT,
		parent: undefined,
		reactions: {},
		referenceId: expect.stringMatching(/^[a-zA-Z0-9]{64}$/),
		systemMessage: '',
		timestamp: convertToUnix(new Date('2020-01-01T20:00:00')),
		token: TOKEN,
		silent: false,
		threadId: undefined,
		isThread: undefined,
		threadReplies: undefined,
		threadTitle: undefined,
	}

	const parent = {
		id: 123,
		token: TOKEN,
		message: 'hello',
	}

	const textFile = {
		type: 'text/plain',
		name: 'new-name.txt',
	}
	const textFilePayload = {
		...defaultPayload,
		message: '{file}',
		uploadId: UPLOAD_ID,
		index: `temp_${UPLOAD_ID}_1`,
		file: textFile,
		localUrl: 'local-url://original-name.txt',
	}
	const textFileResult = {
		...defaultResult,
		id: `temp-${UPLOAD_ID}-001`,
		message: '{file}',
		messageParameters: {
			file: {
				type: 'file',
				file: textFile,
				mimetype: 'text/plain',
				id: `temp-${UPLOAD_ID}-001`,
				name: 'new-name.txt',
				uploadId: UPLOAD_ID,
				localUrl: 'local-url://original-name.txt',
				index: `temp_${UPLOAD_ID}_1`,
			},
		},
		referenceId: expect.stringMatching(/^[a-f0-9]{60}-[0-9]{3}$/),
	}

	const audioFile = {
		type: 'audio/wav',
		name: 'Voice message 2020-01-01 20-00-00 (Note to self).wav',
	}
	const audioFilePayload = {
		...defaultPayload,
		message: '{file}',
		messageType: MESSAGE.TYPE.VOICE_MESSAGE,
		uploadId: UPLOAD_ID,
		index: `temp_${UPLOAD_ID}_1`,
		file: audioFile,
		localUrl: 'local-url://original-name.txt',
	}
	const audioFileResult = {
		...defaultResult,
		id: `temp-${UPLOAD_ID}-001`,
		message: '{file}',
		messageType: MESSAGE.TYPE.VOICE_MESSAGE,
		messageParameters: {
			file: {
				type: 'file',
				file: audioFile,
				mimetype: 'audio/wav',
				id: `temp-${UPLOAD_ID}-001`,
				name: 'Voice message 2020-01-01 20-00-00 (Note to self).wav',
				uploadId: UPLOAD_ID,
				localUrl: 'local-url://original-name.txt',
				index: `temp_${UPLOAD_ID}_1`,
			},
		},
		referenceId: expect.stringMatching(/^[a-f0-9]{60}-[0-9]{3}$/),
	}
	const threadPayload = {
		...defaultPayload,
		threadId: 123,
		isThread: true,
	}
	const threadResult = {
		...defaultResult,
		threadId: 123,
		isThread: true,
	}

	const tests = [
		[defaultPayload, defaultResult],
		[{ ...defaultPayload, parent }, { ...defaultResult, parent }],
		[textFilePayload, textFileResult],
		[audioFilePayload, audioFileResult],
		[threadPayload, threadResult],
	]

	it.each(tests)('test case %# to match expected result', (payload, result) => {
		const temporaryMessage = prepareTemporaryMessage(payload)
		expect(temporaryMessage).toStrictEqual(result)
	})
})
