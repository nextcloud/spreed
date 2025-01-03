/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Get the first selection range in the element if any or null
 * @param element - Element to get the selection range in
 */
export function getCurrentSelectionRange(element?: HTMLElement) {
	const selection = window.getSelection()
	if (selection && selection.rangeCount > 0) {
		if (!element) {
			return selection.getRangeAt(0)
		}
		// Return the first range that is within the element
		for (let i = 0; i < selection.rangeCount; i++) {
			const range = selection.getRangeAt(i)
			if (isRangeWithinElement(range, element)) {
				return range
			}
		}
	}
	return null
}

/**
 * Select a range
 * @param range - Selection range
 * @param element - Only select the range if it is within the element
 */
export function selectRange(range: Range, element?: HTMLElement) {
	if (element && !isRangeWithinElement(range, element)) {
		return
	}
	const selection = window.getSelection()!
	selection.removeAllRanges()
	selection.addRange(range)
}

/**
 * Get a range at the end of the element
 * @param element - Element
 */
export function getRangeAtEnd(element: HTMLElement) {
	const range = document.createRange()
	range.selectNodeContents(element)
	range.collapse()
	return range
}

/**
 * Insert text at a specific selection range
 * @param text - Text to insert
 * @param range - Selection range to insert the text to
 */
export function insertTextAtRange(text: string, range: Range) {
	const textNode = document.createTextNode(text)
	range.deleteContents()
	range.insertNode(textNode)
	range.collapse(false)
}

/**
 * Insert text to the element
 * @param text - Text to insert
 * @param element - Element to insert the text to
 * @param range - Selection range to insert the text at, otherwise at the end of the element
 */
export function insertTextInElement(text: string, element: HTMLElement, range?: Range | null) {
	// Fallback to the end of the element
	range = range && isRangeWithinElement(range, element)
		? range
		: getRangeAtEnd(element)
	insertTextAtRange(text, range)
}

/**
 * Check if a range is within an element
 * @param range - Range to check
 * @param element - Element to check
 */
export function isRangeWithinElement(range?: Range, element?: HTMLElement) {
	if (!range || !element) {
		return false
	}
	return element.contains(range.commonAncestorContainer)
}
