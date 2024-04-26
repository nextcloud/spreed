/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const getTranslationLanguages = async function(options) {
	return axios.get(generateOcsUrl('/translation/languages', undefined, options), options)
}

const translateText = async function(text, fromLanguage, toLanguage, options) {
	return axios.post(generateOcsUrl('/translation/translate', undefined, options), {
		text,
		fromLanguage,
		toLanguage,
	}, options)
}

export { getTranslationLanguages, translateText }
