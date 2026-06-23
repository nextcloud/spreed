/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	TranslationGetLanguagesResponse,
	TranslationTranslateParams,
	TranslationTranslateResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 *
 * @param options
 */
async function getTranslationLanguages(options?: AxiosRequestConfig): TranslationGetLanguagesResponse {
	return axios.get(generateOcsUrl('/translation/languages'), options)
}

/**
 *
 * @param text
 * @param fromLanguage
 * @param toLanguage
 * @param options
 */
async function translateText(
	text: TranslationTranslateParams['text'],
	fromLanguage: TranslationTranslateParams['fromLanguage'],
	toLanguage: TranslationTranslateParams['toLanguage'],
	options?: AxiosRequestConfig,
): TranslationTranslateResponse {
	return axios.post(generateOcsUrl('/translation/translate'), {
		text,
		fromLanguage,
		toLanguage,
	}, options)
}

export { getTranslationLanguages, translateText }
