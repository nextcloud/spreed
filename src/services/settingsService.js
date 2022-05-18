/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license AGPL-3.0-or-later
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
import BrowserStorage from './BrowserStorage.js'

/**
 * Sets the attachment folder setting for the user
 *
 * @param {string} path The name of the folder
 * @return {object} The axios response
 */
const setAttachmentFolder = async function(path) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
		key: 'attachment_folder',
		value: path,
	})
}

/**
 * Sets the read status privacy setting for the user
 *
 * @param {number} privacy The selected value, either 0 or 1
 * @return {object} The axios response
 */
const setReadStatusPrivacy = async function(privacy) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
		key: 'read_status_privacy',
		value: privacy,
	})
}

/**
 * Save the SIP settings
 *
 * @param {Array<string>} sipGroups The groups allowed to enable SIP on a conversation
 * @param {string} sharedSecret The shared secret which is used by the SIP server to authenticate
 * @param {string} dialInInfo The dial-in Information displayed in the email and sidebar
 * @return {object} The axios response
 */
const setSIPSettings = async function(sipGroups, sharedSecret, dialInInfo) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/sip'), {
		sipGroups,
		sharedSecret,
		dialInInfo,
	})
}

const setPlaySounds = async function(isGuest, enabled) {
	const savableValue = enabled ? 'yes' : 'no'
	if (!isGuest) {
		return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
			key: 'play_sounds',
			value: savableValue,
		})
	} else {
		BrowserStorage.setItem('play_sounds', savableValue)
	}
}

export {
	setAttachmentFolder,
	setReadStatusPrivacy,
	setSIPSettings,
	setPlaySounds,
}
