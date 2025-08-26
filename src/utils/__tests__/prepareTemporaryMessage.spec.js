/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import { ATTENDEE, MESSAGE } from '../../constants.ts'
import { prepareTemporaryMessage } from '../prepareTemporaryMessage.ts'

describe('prepareTemporaryMessage', () => {
	const TOKEN = 'XXTOKENXX'

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
		timestamp: 0,
		token: TOKEN,
		silent: false,
		threadId: undefined,
		isThread: undefined,
	}

	const parent = {
		id: 123,
		token: TOKEN,
		message: 'hello',
	}

	const textFile = {
		type: 'text/plain',
		name: 'original-name.txt',
		newName: 'new-name.txt',
	}
	const textFilePayload = {
		...defaultPayload,
		message: '{file}',
		uploadId: 'upload-id-1',
		index: 'upload-index-1',
		file: textFile,
		localUrl: 'local-url://original-name.txt',
	}
	const textFileResult = {
		...defaultResult,
		id: expect.stringMatching(/^temp-1577908800000-upload-id-1-0\.[0-9]*$/),
		message: '{file}',
		messageParameters: {
			file: {
				type: 'file',
				file: textFile,
				mimetype: 'text/plain',
				id: expect.stringMatching(/^temp-1577908800000-upload-id-1-0\.[0-9]*$/),
				name: 'new-name.txt',
				uploadId: 'upload-id-1',
				localUrl: 'local-url://original-name.txt',
				index: 'upload-index-1',
			},
		},
	}

	const audioFile = {
		type: 'audio/wav',
		name: 'Talk recording from 2020-01-01 20-00-00.wav',
	}
	const audioFilePayload = {
		...defaultPayload,
		message: '{file}',
		messageType: MESSAGE.TYPE.VOICE_MESSAGE,
		uploadId: 'upload-id-1',
		index: 'upload-index-1',
		file: audioFile,
		localUrl: 'local-url://original-name.txt',
	}
	const audioFileResult = {
		...defaultResult,
		id: expect.stringMatching(/^temp-1577908800000-upload-id-1-0\.[0-9]*$/),
		message: '{file}',
		messageType: MESSAGE.TYPE.VOICE_MESSAGE,
		messageParameters: {
			file: {
				type: 'file',
				file: audioFile,
				mimetype: 'audio/wav',
				id: expect.stringMatching(/^temp-1577908800000-upload-id-1-0\.[0-9]*$/),
				name: 'Talk recording from 2020-01-01 20-00-00.wav',
				uploadId: 'upload-id-1',
				localUrl: 'local-url://original-name.txt',
				index: 'upload-index-1',
			},
		},
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
