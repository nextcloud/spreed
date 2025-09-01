/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Converts given payload to Data URI scheme
 * data:[<mediatype>][;base64],<data>
 *
 * @param payload data to convert
 * @param mediatype a MIME type string
 * @param base64Token an optional base64 token if non-textual data is given
 */
function convertToDataURI(payload: string, mediatype: string = 'text/plain;charset=US-ASCII', base64Token: string = ''): string {
	return 'data:' + mediatype + base64Token + ',' + encodeURIComponent(payload)
}

/**
 * Converts given JS object to Data URI scheme
 *
 * @param payload JS object to convert
 */
function convertToJSONDataURI(payload: object): string {
	return convertToDataURI(JSON.stringify(payload, null, 2), 'application/json;charset=utf-8')
}

export {
	convertToDataURI,
	convertToJSONDataURI,
}
