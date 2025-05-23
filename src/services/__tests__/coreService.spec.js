/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { SHARE } from '../../constants.ts'
import { autocompleteQuery } from '../coreService.ts'

jest.mock('@nextcloud/axios', () => ({
	get: jest.fn(),
}))

// Test requests when federations invite are enabled
jest.mock('@nextcloud/capabilities', () => ({
	getCapabilities: jest.fn(() => ({
		spreed: {
			features: ['federation-v1'],
			'features-local': [],
			config: { federation: { enabled: true, 'outgoing-enabled': true } },
			'config-local': { federation: [] },
		},
	})),
}))

describe('coreService', () => {
	afterEach(() => {
		// cleaning up the mess left behind the previous test
		jest.clearAllMocks()
	})

	/**
	 * @param {string} token The conversation to search in
	 * @param {boolean} onlyUsers Whether or not to only search for users
	 * @param {Array} expectedShareTypes The expected search types to look for
	 */
	function testAutocompleteQuery(token, onlyUsers, expectedShareTypes) {
		autocompleteQuery(
			{
				searchText: 'search-text',
				token,
				onlyUsers,
			},
			{
				dummyOption: true,
			},
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
			},
		)
	}

	test('autocompleteQuery with only users', () => {
		testAutocompleteQuery(
			'conversation-token',
			true,
			[
				SHARE.TYPE.USER,
			],
		)
	})

	test('autocompleteQuery with other share types', () => {
		testAutocompleteQuery(
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

	test('autocompleteQuery with other share types and a new token', () => {
		testAutocompleteQuery(
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
