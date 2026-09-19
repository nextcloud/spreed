/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	TaskProcessingScheduleParams,
	TaskProcessingScheduleResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { TASK_PROCESSING } from '../constants.ts'

/**
 * Schedule a task to translate the given text
 *
 * @param text The text to translate
 * @param originLanguage The language to translate from ('detect_language' to detect it automatically)
 * @param targetLanguage The language to translate into
 * @param [options] Axios request options
 */
async function scheduleTranslateTask(
	text: string,
	originLanguage: string,
	targetLanguage: string,
	options?: AxiosRequestConfig,
): TaskProcessingScheduleResponse {
	return axios.post(generateOcsUrl('taskprocessing/schedule'), {
		type: TASK_PROCESSING.TYPE.TRANSLATE,
		appId: 'spreed',
		input: {
			input: text,
			origin_language: originLanguage,
			target_language: targetLanguage,
		},
	} as TaskProcessingScheduleParams, options)
}

export { scheduleTranslateTask }
