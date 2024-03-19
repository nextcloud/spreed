import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { searchPossibleConversations } from './conversationsService.js'
import { SHARE } from '../constants.js'

jest.mock('@nextcloud/axios', () => ({
	get: jest.fn(),
}))

jest.mock('@nextcloud/capabilities', () => ({
	getCapabilities: jest.fn(() => ({
		spreed: {
			features: ['federation-v1'],
			config: { federation: { enabled: true, 'outgoing-enabled': true } },
		},
	}))
}))

describe('conversationsService', () => {
	afterEach(() => {
		// cleaning up the mess left behind the previous test
		jest.clearAllMocks()
	})

	/**
	 * @param {string} token The conversation to search in
	 * @param {boolean} onlyUsers Whether or not to only search for users
	 * @param {Array} expectedShareTypes The expected search types to look for
	 */
	function testSearchPossibleConversations(token, onlyUsers, expectedShareTypes) {
		searchPossibleConversations(
			{
				searchText: 'search-text',
				token,
				onlyUsers,
			},
			{
				dummyOption: true,
			}
		)
		expect(axios.get).toHaveBeenCalledWith(
			generateOcsUrl('core/autocomplete/get'),
			{
				dummyOption: true,
				params: {
					itemId: token,
					itemType: 'call',
					search: 'search-text',
					shareTypes: expectedShareTypes,
				},
			}
		)
	}

	test('searchPossibleConversations with only users', () => {
		testSearchPossibleConversations(
			'conversation-token',
			true,
			[
				SHARE.TYPE.USER,
			],
		)
	})

	test('searchPossibleConversations with other share types', () => {
		testSearchPossibleConversations(
			'conversation-token',
			false,
			[
				SHARE.TYPE.USER,
				SHARE.TYPE.GROUP,
				SHARE.TYPE.CIRCLE,
				SHARE.TYPE.EMAIL,
				SHARE.TYPE.REMOTE,
			],
		)
	})

	test('searchPossibleConversations with other share types and a new token', () => {
		testSearchPossibleConversations(
			'new',
			false,
			[
				SHARE.TYPE.USER,
				SHARE.TYPE.GROUP,
				SHARE.TYPE.CIRCLE,
				SHARE.TYPE.REMOTE,
			],
		)
	})
})
