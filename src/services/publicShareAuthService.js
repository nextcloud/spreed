/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Gets the public share auth conversation token for a given share token.
 *
 * @param {string} shareToken the token of the share
 * @return {string} the conversation token
 */
const getPublicShareAuthConversationToken = async function(shareToken) {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v1/publicshareauth'), {
		shareToken,
	})
	return response.data.ocs.data.token
}

export {
	getPublicShareAuthConversationToken,
}
