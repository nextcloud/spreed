/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
 * Sets the typing status privacy setting for the user
 *
 * @param {number} privacy The selected value, either 0 or 1
 * @return {object} The axios response
 */
const setTypingStatusPrivacy = async function(privacy) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
		key: 'typing_privacy',
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
	setTypingStatusPrivacy,
	setSIPSettings,
	setPlaySounds,
}
