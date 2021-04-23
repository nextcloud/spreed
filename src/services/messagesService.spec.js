import mockAxios from '../__mocks__/axios'
import { generateOcsUrl } from '@nextcloud/router'
import {
	fetchMessages,
	lookForNewMessages,
	postNewMessage,
	deleteMessage,
	postRichObjectToConversation,
	updateLastReadMessage,
} from './messagesService'

describe('messagesService', () => {
	afterEach(() => {
		// cleaning up the mess left behind the previous test
		mockAxios.reset()
	})

	test('fetchMessages calls the chat API endpoint excluding last known', () => {
		fetchMessages({
			token: 'XXTOKENXX',
			lastKnownMessageId: 1234,
			includeLastKnown: 0,
		}, {
			dummyOption: true,
		})

		expect(mockAxios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX',
			{
				dummyOption: true,
				params: {
					setReadMarker: 0,
					lookIntoFuture: 0,
					lastKnownMessageId: 1234,
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

		expect(mockAxios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX',
			{
				dummyOption: true,
				params: {
					setReadMarker: 0,
					lookIntoFuture: 0,
					lastKnownMessageId: 1234,
					includeLastKnown: 1,
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

		expect(mockAxios.get).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX',
			{
				dummyOption: true,
				params: {
					setReadMarker: 0,
					lookIntoFuture: 1,
					lastKnownMessageId: 1234,
					includeLastKnown: 0,
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
			parent: 111,
		})

		expect(mockAxios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX',
			{
				message: 'hello world!',
				actorDisplayName: 'actor-display-name',
				referenceId: 'reference-id',
				replyTo: 111,
			}
		)
	})

	test('deleteMessage calls the chat API endpoint', () => {
		deleteMessage({
			token: 'XXTOKENXX',
			id: 1234,
		})

		expect(mockAxios.delete).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX/1234'
		)
	})

	test('postRichObjectToConversation calls the chat API endpoint', () => {
		postRichObjectToConversation('XXTOKENXX', {
			objectType: 'deck',
			objectId: 999,
			metaData: '{"x":1}',
			referenceId: 'reference-id',
		})

		expect(mockAxios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX/share',
			{
				objectType: 'deck',
				objectId: 999,
				metaData: '{"x":1}',
				referenceId: 'reference-id',
			}
		)
	})

	test('postRichObjectToConversation without reference id will generate one', () => {
		postRichObjectToConversation('XXTOKENXX', {
			objectType: 'deck',
			objectId: 999,
			metaData: '{"x":1}',
		})

		const lastReq = mockAxios.lastReqGet()
		expect(lastReq.url)
			.toBe(generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX/share')
		expect(lastReq.data.objectType).toBe('deck')
		expect(lastReq.data.objectId).toBe(999)
		expect(lastReq.data.metaData).toBe('{"x":1}')
		expect(lastReq.data.referenceId).toEqual(expect.stringMatching(/^[a-z0-9]{40}$/))
	})

	test('updateLastReadMessage calls the chat API endpoint', () => {
		updateLastReadMessage('XXTOKENXX', 1234)

		expect(mockAxios.post).toHaveBeenCalledWith(
			generateOcsUrl('apps/spreed/api/v1/chat', 2) + 'XXTOKENXX/read',
			{
				lastReadMessage: 1234,
			}
		)
	})
})
