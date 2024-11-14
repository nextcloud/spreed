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

const setPlaySounds = async function(hasUserAccount, value) {
	if (hasUserAccount) {
		await axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
			key: 'play_sounds',
			value,
		})
	} else {
		BrowserStorage.setItem('play_sounds', value)
	}
}

const setStartWithoutMedia = async function(value) {
	await setUserConfig('spreed', 'calls_start_without_media', value ? 'yes' : 'no')
}

const setBlurVirtualBackground = async function(value) {
	await setUserConfig('spreed', 'blur_virtual_background', value ? 'yes' : 'no')
}

/**
 * Set user config using provisioning API
 *
 * @param {string} appId - app id
 * @param {string} configKey - key of the config to set
 * @param {string} configValue - value to set
 */
const setUserConfig = async function(appId, configKey, configValue) {
	await axios.post(generateOcsUrl('apps/provisioning_api/api/v1/config/users/{appId}/{configKey}', { appId, configKey }), {
		configValue,
	})
}

export {
	setAttachmentFolder,
	setBlurVirtualBackground,
	setReadStatusPrivacy,
	setTypingStatusPrivacy,
	setSIPSettings,
	setPlaySounds,
	setStartWithoutMedia,
	setUserConfig,
}
