/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/**
 * @typedef {object} ClipboardContent
 * @property {('file'|'text'|'none')} kind content type (file or text)
 * @property {File[] | FileList | undefined} files files array
 * @property {string | undefined} text text content
 */

/**
 * Fetches the clipboard content from the event.
 *
 * @param {ClipboardEvent} event native event
 * @return {ClipboardContent}
 */
const fetchClipboardContent = function(event) {
	const clipboardData = event.clipboardData

	if (!clipboardData) {
		return { kind: 'none' }
	}

	if (clipboardData.files && clipboardData.files.length > 0) {
		return { kind: 'file', files: clipboardData.files }
	}

	if (clipboardData.items && clipboardData.items.length > 0) {
		const files = []
		for (let i = 0; i < clipboardData.items.length; i++) {
			if (clipboardData.items[i].kind === 'file') {
				files.push(clipboardData.items[i].getAsFile())
			}
		}

		if (files.length > 0) {
			return { kind: 'file', files }
		}
	}

	const text = clipboardData.getData('text/plain')

	return { kind: 'text', text }
}

export {
	fetchClipboardContent,
}
