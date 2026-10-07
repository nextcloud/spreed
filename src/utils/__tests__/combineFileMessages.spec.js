/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { MESSAGE } from '../../constants.ts'
import { combineFileMessages } from '../combineFileMessages.ts'

describe('combineFileMessages', () => {
	const UPLOAD_HASH = 'a'.repeat(60)

	const deletedMessage = (id, referenceId) => ({
		id,
		message: 'Message deleted by you',
		messageParameters: {},
		messageType: MESSAGE.TYPE.COMMENT_DELETED,
		systemMessage: '',
		referenceId,
	})

	const fileShare = (id, referenceId, message = '{file}') => ({
		id,
		message,
		messageParameters: { file: { type: 'file', name: `file${id}.png`, mimetype: 'image/png' } },
		messageType: MESSAGE.TYPE.COMMENT,
		systemMessage: '',
		referenceId,
	})

	it('combines file shares of the same upload', () => {
		const result = combineFileMessages([
			fileShare(1, `${UPLOAD_HASH}-1`),
			fileShare(2, `${UPLOAD_HASH}-2`),
			fileShare(3, `${UPLOAD_HASH}-3`),
		])

		expect(result).toHaveLength(1)
		expect(result[0]).toMatchObject({
			id: 3,
			message: '{file-1} {file-2} {file-3}',
			combinedMessageIds: [1, 2, 3],
		})
		expect(result[0].messageParameters['file-2']).toMatchObject({ name: 'file2.png', referenceId: `${UPLOAD_HASH}-2` })
	})

	it('uses the caption of the last file share of the upload', () => {
		const result = combineFileMessages([
			fileShare(1, `${UPLOAD_HASH}-1`),
			fileShare(2, `${UPLOAD_HASH}-2`, 'Caption'),
			fileShare(3, `${'b'.repeat(60)}-1`),
		])

		expect(result.map((message) => message.id)).toEqual([2, 3])
		expect(result[0]).toMatchObject({ message: 'Caption', combinedMessageIds: [1, 2] })
		expect(result[1]).not.toHaveProperty('combinedMessageIds')
	})

	it('combines deleted file shares of the same upload', () => {
		const result = combineFileMessages([
			deletedMessage(1, `${UPLOAD_HASH}-1`),
			deletedMessage(2, `${UPLOAD_HASH}-2`),
			deletedMessage(3, `${UPLOAD_HASH}-3`),
		])

		expect(result).toHaveLength(1)
		expect(result[0]).toMatchObject({ id: 3, combinedMessageIds: [1, 2, 3] })
	})

	it('does not combine deleted messages of different uploads', () => {
		const result = combineFileMessages([
			deletedMessage(1, `${UPLOAD_HASH}-1`),
			deletedMessage(2, `${'b'.repeat(60)}-1`),
			deletedMessage(3, ''),
			deletedMessage(4, ''),
		])

		expect(result.map((message) => message.id)).toEqual([1, 2, 3, 4])
		expect(result.some((message) => 'combinedMessageIds' in message)).toBe(false)
	})

	it('does not combine a deleted message with a file share of the same upload', () => {
		const result = combineFileMessages([
			deletedMessage(1, `${UPLOAD_HASH}-1`),
			fileShare(2, `${UPLOAD_HASH}-2`),
		])

		expect(result.map((message) => message.id)).toEqual([1, 2])
	})

	it('combines deleted and remaining file shares of a partially deleted upload separately', () => {
		const result = combineFileMessages([
			deletedMessage(1, `${UPLOAD_HASH}-1`),
			deletedMessage(2, `${UPLOAD_HASH}-2`),
			fileShare(3, `${UPLOAD_HASH}-3`),
			fileShare(4, `${UPLOAD_HASH}-4`),
		])

		expect(result).toHaveLength(2)
		expect(result[0]).toMatchObject({
			id: 2,
			messageType: MESSAGE.TYPE.COMMENT_DELETED,
			message: 'Message deleted by you',
			combinedMessageIds: [1, 2],
		})
		expect(result[1]).toMatchObject({
			id: 4,
			message: '{file-1} {file-2}',
			combinedMessageIds: [3, 4],
		})
	})
})
