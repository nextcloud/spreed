/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { CHAT } from '../../constants.js'
import {
	fetchMessages,
	getMessageContext,
	lookForNewMessages,
	postNewMessage,
	deleteMessage,
	editMessage,
	postRichObjectToConversation,
	updateLastReadMessage,
} from '../messagesService.ts'
import {
	addReactionToMessage,
	removeReactionFromMessage,
	getReactionsDetails,
} from '../reactionsService.ts'
import {
	getTranslationLanguages,
	translateText,
} from '../translationService.js'

jest.mock('@nextcloud/axios', () => ({
	get: jest.fn(),
	post: jest.fn(),
	put: jest.fn(),
	delete: jest.fn(),
}))

describe('messagesService', () => {
	afterEach(() => {
		// cleaning up the mess left behind the previous test
		jest.clearAllMocks()
	})

	test('fetchMessages calls the chat API endpoint excluding last known', () => {
		fetchMessages({
			token: 'XXTOKENXX',
			lastKnownMessageId: 1234,
			includeLastKnown: 0,
		}, {
			dummyOption: true,
		})

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX'),
			{
				dummyOption: true,
				params: {
					setReadMarker: 0,
					lookIntoFuture: 0,
					lastKnownMessageId: 1234,
					limit: CHAT.FETCH_LIMIT,
					includeLastKnown: 0,
				},
			}
		)
	})

	test('fetchMessages calls the chat API endpoint including last known', () => {
		fetchMessages({
			token: 'XXTOKENXX',
			lastKnownMessageId: 1234,
			includeLastKnown: 1,
		}, {
			dummyOption: true,
		})

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX'),
			{
				dummyOption: true,
				params: {
					setReadMarker: 0,
					lookIntoFuture: 0,
					lastKnownMessageId: 1234,
					limit: CHAT.FETCH_LIMIT,
					includeLastKnown: 1,
				},
			}
		)
	})

	test('getMessageContext calls the chat API endpoint within specific messageId', () => {
		getMessageContext({
			token: 'XXTOKENXX',
			messageId: 1234,
		}, {
			dummyOption: true,
		})

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX/1234/context'),
			{
				dummyOption: true,
				params: {
					limit: CHAT.FETCH_LIMIT / 2,
				},
			}
		)
	})

	test('lookForNewMessages calls the chat API endpoint excluding last known', () => {
		lookForNewMessages({
			token: 'XXTOKENXX',
			lastKnownMessageId: 1234,
		}, {
			dummyOption: true,
		})

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX'),
			{
				dummyOption: true,
				params: {
					setReadMarker: 0,
					lookIntoFuture: 1,
					lastKnownMessageId: 1234,
					limit: CHAT.FETCH_LIMIT,
					includeLastKnown: 0,
					markNotificationsAsRead: 0,
				},
			}
		)
	})

	test('postNewMessage calls the chat API endpoint', () => {
		postNewMessage({
			token: 'XXTOKENXX',
			message: 'hello world!',
			actorDisplayName: 'actor-display-name',
			referenceId: 'reference-id',
			parent: { id: 111 },
		}, {
			silent: false,
			dummyOption: true,
		})

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX'),
			{
				message: 'hello world!',
				actorDisplayName: 'actor-display-name',
				referenceId: 'reference-id',
				replyTo: 111,
				silent: false,
			},
			{
				dummyOption: true,
			}
		)
	})

	test('deleteMessage calls the chat API endpoint', () => {
		deleteMessage({
			token: 'XXTOKENXX',
			id: 1234,
		}, { dummyOption: true })

		expect(axios.delete).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX/1234'),
			{ dummyOption: true }
		)
	})

	test('editMessage calls the chat API endpoint', () => {
		editMessage({
			token: 'XXTOKENXX',
			messageId: 1234,
			updatedMessage: 'edited message text',
		}, { dummyOption: true })

		expect(axios.put).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX/1234'),
			{ message: 'edited message text' },
			{ dummyOption: true }
		)
	})

	test('postRichObjectToConversation calls the chat API endpoint', () => {
		postRichObjectToConversation('XXTOKENXX', {
			objectType: 'deck',
			objectId: 999,
			metaData: '{"x":1}',
			referenceId: 'reference-id',
		}, { dummyOption: true })

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX/share'),
			{
				objectType: 'deck',
				objectId: 999,
				metaData: '{"x":1}',
				referenceId: 'reference-id',
			},
			{ dummyOption: true }
		)
	})

	test('postRichObjectToConversation without reference id will generate one', () => {
		postRichObjectToConversation('XXTOKENXX', {
			objectType: 'deck',
			objectId: 999,
			metaData: '{"x":1}',
		}, { dummyOption: true })

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX/share'),
			{
				objectType: 'deck',
				objectId: 999,
				metaData: '{"x":1}',
				referenceId: expect.stringMatching(/^[a-z0-9]{64}$/),
			},
			{ dummyOption: true }
		)
	})

	test('updateLastReadMessage calls the chat API endpoint', () => {
		updateLastReadMessage('XXTOKENXX', 1234, { dummyOption: true })

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat/XXTOKENXX/read'),
			{
				lastReadMessage: 1234,
			},
			{ dummyOption: true }
		)
	})

	test('addReactionToMessage calls the reaction API endpoint', () => {
		addReactionToMessage('XXTOKENXX', 1234, '👍', { dummyOption: true })

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/reaction/XXTOKENXX/1234'),
			{
				reaction: '👍',
			},
			{ dummyOption: true }
		)
	})

	test('removeReactionFromMessage calls the reaction API endpoint', () => {
		removeReactionFromMessage('XXTOKENXX', 1234, '👍', { dummyOption: true })

		expect(axios.delete).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/reaction/XXTOKENXX/1234'),
			{
				dummyOption: true,
				params: {
					reaction: '👍',
				},
			}
		)
	})

	test('getReactionsDetails calls the reaction API endpoint', () => {
		getReactionsDetails('XXTOKENXX', 1234, { dummyOption: true })

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/reaction/XXTOKENXX/1234'),
			{
				dummyOption: true,
			}
		)
	})

	test('getTranslationLanguages calls the translation API endpoint', () => {
		getTranslationLanguages({ dummyOption: true })

		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('translation/languages'),
			{
				dummyOption: true,
			}
		)
	})

	test('translateText calls the translation API endpoint', () => {
		translateText('text to translate', 'en', 'de', { dummyOption: true })

		expect(axios.post).toHaveBeenCalledWith(
			generateOcsUrl('translation/translate'),
			{
				text: 'text to translate',
				fromLanguage: 'en',
				toLanguage: 'de',
			},
			{
				dummyOption: true,
			}
		)
	})
})
