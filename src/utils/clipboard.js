/**
 * @typedef {Object} ClipboardContent
 * @property {('file'|'text'|'none')} kind content type (file or text)
 * @property {File[] | FileList | undefined} files files array
 * @property {String | undefined} text text content
 */

/**
 * Fetches the clipboard content from the event.
 *
 * @param {ClipboardEvent} event native event
 * @returns {ClipboardContent}
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
			return { kind: 'file', files: files }
		}
	}

	const text = clipboardData.getData('text/plain')

	return { kind: 'text', text: text }
}

export {
	fetchClipboardContent,
}
