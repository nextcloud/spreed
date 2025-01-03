/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Get the current selection range if any
 */
export function getCurrentSelectionRange() {
	const selection = window.getSelection()!
	if (selection.rangeCount > 0) {
		return selection.getRangeAt(0)
	}
	return null
}

/**
 * Select a specific range as the current selection range
 * @param range - Selection range
 */
export function setCurrentSelectionRange(range: Range) {
	const selection = window.getSelection()!
	selection.removeAllRanges()
	selection.addRange(range)
}

/**
 * Get a range at the end of the contenteditable element
 * @param contenteditable - Contenteditable
 */
export function getRangeAtEnd(contenteditable: HTMLElement) {
	const range = document.createRange()
	range.selectNodeContents(contenteditable)
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
	range.setStartAfter(textNode)
	range.collapse(true)
}
