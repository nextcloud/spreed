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

/**
 * Triggers a file download from a Data URL
 *
 * @param dataUrl the data URL to download
 * @param filename the filename for the downloaded file
 */
function downloadDataURL(dataUrl: string, filename: string): void {
	const a = document.createElement('a')
	a.href = dataUrl
	a.download = filename
	a.click()
}

/**
 * Triggers a file download from a Blob
 *
 * @param blob the blob to download
 * @param filename the filename for the downloaded file
 */
function downloadBlob(blob: Blob, filename: string): void {
	const url = URL.createObjectURL(blob)
	const a = document.createElement('a')
	a.href = url
	a.download = filename
	a.click()
	URL.revokeObjectURL(url)
}

export {
	convertToDataURI,
	convertToJSONDataURI,
	downloadBlob,
	downloadDataURL,
}
