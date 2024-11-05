/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { getTalkConfig, hasTalkFeature } from './CapabilitiesManager.ts'
import { SHARE } from '../constants.js'

const canInviteToFederation = hasTalkFeature('local', 'federation-v1')
	&& getTalkConfig('local', 'federation', 'enabled')
	&& getTalkConfig('local', 'federation', 'outgoing-enabled')

type SearchPayload = {
	searchText: string
	token?: string | 'new'
	onlyUsers?: boolean
	forceTypes?: typeof SHARE.TYPE[keyof typeof SHARE.TYPE][]
}

/**
 * Fetch possible conversations
 *
 * @param payload the wrapping object;
 * @param payload.searchText The string that will be used in the search query.
 * @param [payload.token] The token of the conversation (if any) | 'new' for new conversations
 * @param [payload.onlyUsers] Whether to return only registered users
 * @param [payload.forceTypes] Whether to force some types to be included in query
 * @param options options
 */
const autocompleteQuery = async function({ searchText, token = 'new', onlyUsers = false, forceTypes = [] }: SearchPayload, options: object) {
	const shareTypes = onlyUsers
		? [SHARE.TYPE.USER].concat(forceTypes)
		: [
			SHARE.TYPE.USER,
			SHARE.TYPE.GROUP,
			SHARE.TYPE.CIRCLE,
			token !== 'new' ? SHARE.TYPE.EMAIL : null,
			canInviteToFederation ? SHARE.TYPE.REMOTE : null,
		].filter(type => type !== null).concat(forceTypes)

	return axios.get(generateOcsUrl('core/autocomplete/get'), {
		...options,
		params: {
			search: searchText,
			itemType: 'call',
			itemId: token,
			shareTypes,
		},
	})
}

export {
	autocompleteQuery,
}
