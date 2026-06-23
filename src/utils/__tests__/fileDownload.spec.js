/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
	convertToDataURI,
	convertToJSONDataURI,
	downloadBlob,
	downloadDataURL,
} from '../fileDownload.ts'

describe('fileDownload', () => {
	describe('convertToDataURI', () => {
		it('converts a plain string to a data URI with default media type', () => {
			const result = convertToDataURI('hello')
			expect(result).toBe('data:text/plain;charset=US-ASCII,hello')
		})

		it('converts with a custom media type', () => {
			const result = convertToDataURI('<svg/>', 'image/svg+xml;charset=utf-8')
			expect(result).toBe('data:image/svg+xml;charset=utf-8,%3Csvg%2F%3E')
		})

		it('encodes special characters', () => {
			const result = convertToDataURI('hello world & more')
			expect(result).toContain('hello%20world%20%26%20more')
		})
	})

	describe('convertToJSONDataURI', () => {
		it('converts an object to a JSON data URI', () => {
			const obj = { question: 'test', options: ['a', 'b'] }
			const result = convertToJSONDataURI(obj)
			expect(result).toMatch(/^data:application\/json;charset=utf-8,/)
			const decoded = decodeURIComponent(result.replace('data:application/json;charset=utf-8,', ''))
			expect(JSON.parse(decoded)).toEqual(obj)
		})
	})

	describe('downloadDataURL', () => {
		let clickSpy

		beforeEach(() => {
			clickSpy = vi.fn()
			vi.spyOn(document, 'createElement').mockReturnValue({
				href: '',
				download: '',
				click: clickSpy,
			})
		})

		afterEach(() => {
			vi.restoreAllMocks()
		})

		it('creates an anchor element and triggers a click', () => {
			downloadDataURL('data:image/png;base64,abc', 'test.png')

			expect(document.createElement).toHaveBeenCalledWith('a')
			expect(clickSpy).toHaveBeenCalledOnce()
		})
	})

	describe('downloadBlob', () => {
		let clickSpy

		beforeEach(() => {
			clickSpy = vi.fn()
			vi.spyOn(document, 'createElement').mockReturnValue({
				href: '',
				download: '',
				click: clickSpy,
			})
			vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:http://localhost/fake-id')
			vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
		})

		afterEach(() => {
			vi.restoreAllMocks()
		})

		it('creates a blob URL, triggers download, and revokes the URL', () => {
			const blob = new Blob(['test'], { type: 'text/plain' })
			downloadBlob(blob, 'test.txt')

			expect(URL.createObjectURL).toHaveBeenCalledWith(blob)
			expect(clickSpy).toHaveBeenCalledOnce()
			expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:http://localhost/fake-id')
		})
	})
})
