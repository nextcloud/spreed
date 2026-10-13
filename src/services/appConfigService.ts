/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	AppConfigSetValueParams,
	AppConfigSetValueResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Set app config using provisioning API
 *
 * @param appId - app id
 * @param configKey - key of the config to set
 * @param value - value to set
 */
async function setAppConfig(appId: string, configKey: string, value: string): AppConfigSetValueResponse {
	await confirmPassword()
	return axios.post(generateOcsUrl('apps/provisioning_api/api/v1/config/apps/{appId}/{configKey}', { appId, configKey }), {
		value,
	} as AppConfigSetValueParams)
}

export {
	setAppConfig,
}
