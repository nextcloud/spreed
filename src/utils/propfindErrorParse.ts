/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

type PROPFINDException = {
	response: {
		text: () => Promise<string>
	}
}

/**
 * Parse PROPFIND error when uploading a file and return a readable message.
 *
 * @param exception error object
 */
async function parseUploadError(exception: PROPFINDException) {
	try {
		const responseText = await exception.response.text()
		const parser = new DOMParser()
		const xmlDoc = parser.parseFromString(responseText, 'application/xml')
		const messageElement = xmlDoc.getElementsByTagName('s:message')[0]

		return messageElement?.textContent
	} catch (parseError) {
		console.error(t('spreed', 'Error while parsing a PROPFIND error'))
	}
}

export {
	parseUploadError,
}
