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
	token?: string
	onlyUsers?: boolean
}

/**
 * Fetch possible conversations
 *
 * @param data the wrapping object;
 * @param data.searchText The string that will be used in the search query.
 * @param [data.token] The token of the conversation (if any), or "new" for a new one
 * @param [data.onlyUsers] Only return users
 * @param options options
 */
const autocompleteQuery = async function({ searchText, token, onlyUsers }: SearchPayload, options: object) {
	token = token || 'new'
	onlyUsers = !!onlyUsers

	const shareTypes = [
		SHARE.TYPE.USER,
		!onlyUsers ? SHARE.TYPE.GROUP : null,
		!onlyUsers ? SHARE.TYPE.CIRCLE : null,
		(!onlyUsers && token !== 'new') ? SHARE.TYPE.EMAIL : null,
		(!onlyUsers && canInviteToFederation) ? SHARE.TYPE.REMOTE : null,
	].filter(type => type !== null)

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
