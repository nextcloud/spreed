/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	setSipSettingsParams,
	setSipSettingsResponse,
	setUserSettingsParams,
	setUserSettingsResponse,
	UserPreferencesParams,
	UserPreferencesResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import BrowserStorage from './BrowserStorage.js'

/**
 * Sets the attachment folder setting for the user
 *
 * @param path The name of the folder
 */
async function setAttachmentFolder(path: string): setUserSettingsResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
		key: 'attachment_folder',
		value: path,
	} as setUserSettingsParams)
}

/**
 * Sets the read status privacy setting for the user
 *
 * @param privacy The selected value, either 0 or 1
 */
async function setReadStatusPrivacy(privacy: number): setUserSettingsResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
		key: 'read_status_privacy',
		value: privacy,
	} as setUserSettingsParams)
}

/**
 * Sets the typing status privacy setting for the user
 *
 * @param privacy The selected value, either 0 or 1
 */
async function setTypingStatusPrivacy(privacy: number): setUserSettingsResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
		key: 'typing_privacy',
		value: privacy,
	} as setUserSettingsParams)
}

/**
 * Save the SIP settings
 *
 * @param payload payload
 * @param payload.sipGroups The groups allowed to enable SIP on a conversation
 * @param payload.sharedSecret The shared secret which is used by the SIP server to authenticate
 * @param payload.dialInInfo The dial-in Information displayed in the email and sidebar
 */
async function setSIPSettings({ sipGroups, sharedSecret, dialInInfo }: setSipSettingsParams): setSipSettingsResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/sip'), {
		sipGroups,
		sharedSecret,
		dialInInfo,
	} as setSipSettingsParams)
}

/**
 *
 * @param hasUserAccount
 * @param value
 */
async function setPlaySounds(hasUserAccount: boolean, value: 'yes' | 'no') {
	if (hasUserAccount) {
		return axios.post(generateOcsUrl('apps/spreed/api/v1/settings/user'), {
			key: 'play_sounds',
			value,
		})
	} else {
		BrowserStorage.setItem('play_sounds', value)
	}
}

/**
 *
 * @param value
 */
async function setStartWithoutMedia(value: boolean) {
	return setUserConfig('spreed', 'calls_start_without_media', value ? 'yes' : 'no')
}

/**
 *
 * @param value
 */
async function setBlurVirtualBackground(value: boolean) {
	return setUserConfig('spreed', 'blur_virtual_background', value ? 'yes' : 'no')
}

/**
 *
 * @param value
 */
async function setConversationsListStyle(value: string) {
	return setUserConfig('spreed', 'conversations_list_style', value)
}

/**
 * Set user config using provisioning API
 *
 * @param appId - app id
 * @param configKey - key of the config to set
 * @param configValue - value to set
 */
async function setUserConfig(appId: string, configKey: string, configValue: string): UserPreferencesResponse {
	return axios.post(generateOcsUrl('apps/provisioning_api/api/v1/config/users/{appId}/{configKey}', { appId, configKey }), {
		configValue,
	} as UserPreferencesParams)
}

export {
	setAttachmentFolder,
	setBlurVirtualBackground,
	setConversationsListStyle,
	setPlaySounds,
	setReadStatusPrivacy,
	setSIPSettings,
	setStartWithoutMedia,
	setTypingStatusPrivacy,
	setUserConfig,
}
