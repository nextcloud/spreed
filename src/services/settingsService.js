/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Gets the conversation token for a given file id
 *
 * @param {string} path The name of the folder
 * @returns {Object} The axios response
 */
const setAttachmentFolder = async function(path) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings', 2) + 'user', {
		key: 'attachment_folder',
		value: path,
	})
}

/**
 * Save the SIP settings
 *
 * @param {Array<string>} sipGroups The groups allowed to enable SIP on a conversation
 * @param {string} sharedSecret The shared secret which is used by the SIP server to authenticate
 * @param {string} dialInInfo The dial-in Information displayed in the email and sidebar
 * @returns {Object} The axios response
 */
const setSIPSettings = async function(sipGroups, sharedSecret, dialInInfo) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings', 2) + 'sip', {
		sipGroups,
		sharedSecret,
		dialInInfo,
	})
}

export {
	setAttachmentFolder,
	setSIPSettings,
}
