/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import TranscriptBlock from './TranscriptBlock.vue'

describe('TranscriptBlock.vue', () => {
	const props = {
		token: 'theToken',
		model: {
			attributes: {
				peerId: 'thePeerId',
				actorId: 'theActorId',
				actorType: 'theActorType',
				userId: 'theUserId',
				name: 'The user name',
			},
		},
		chunks: [],
		rightToLeft: false,
	}

	describe('remove last chunk from lines', () => {
		let wrapper
		let lines

		beforeEach(() => {
			wrapper = shallowMount(TranscriptBlock, { props })

			lines = wrapper.vm.$data.lines
		})

		test('no lines', () => {
			wrapper.vm.removeLastChunkFromLines()

			expect(lines.length).toBe(0)
		})

		test('single line with single chunk', () => {
			lines.push({
				firstChunkIndex: 42,
				lastChunkIndex: 42,
			})

			wrapper.vm.removeLastChunkFromLines()

			expect(lines.length).toBe(0)
		})

		test('single line with several chunks', () => {
			lines.push({
				firstChunkIndex: 42,
				lastChunkIndex: 108,
			})

			wrapper.vm.removeLastChunkFromLines()

			expect(lines).toEqual([
				{
					firstChunkIndex: 42,
					lastChunkIndex: 107,
				},
			])
		})

		test('several lines with single chunk', () => {
			lines.push({
				firstChunkIndex: 42,
				lastChunkIndex: 42,
			})
			lines.push({
				firstChunkIndex: 42,
				lastChunkIndex: 42,
			})
			lines.push({
				firstChunkIndex: 42,
				lastChunkIndex: 42,
			})

			wrapper.vm.removeLastChunkFromLines()

			expect(lines.length).toBe(0)
		})

		describe('several lines with several chunks', () => {
			test('last chunk filling last line', () => {
				lines.push({
					firstChunkIndex: 23,
					lastChunkIndex: 42,
				})
				lines.push({
					firstChunkIndex: 108,
					lastChunkIndex: 108,
				})

				wrapper.vm.removeLastChunkFromLines()

				expect(lines).toEqual([
					{
						firstChunkIndex: 23,
						lastChunkIndex: 42,
					},
				])
			})

			test('last chunk filling several lines', () => {
				lines.push({
					firstChunkIndex: 23,
					lastChunkIndex: 42,
				})
				lines.push({
					firstChunkIndex: 108,
					lastChunkIndex: 108,
				})
				lines.push({
					firstChunkIndex: 108,
					lastChunkIndex: 108,
				})
				lines.push({
					firstChunkIndex: 108,
					lastChunkIndex: 108,
				})

				wrapper.vm.removeLastChunkFromLines()

				expect(lines).toEqual([
					{
						firstChunkIndex: 23,
						lastChunkIndex: 42,
					},
				])
			})

			test('last chunk partially in last line', () => {
				lines.push({
					firstChunkIndex: 23,
					lastChunkIndex: 42,
				})
				lines.push({
					firstChunkIndex: 42,
					lastChunkIndex: 108,
				})

				wrapper.vm.removeLastChunkFromLines()

				expect(lines).toEqual([
					{
						firstChunkIndex: 23,
						lastChunkIndex: 42,
					},
					{
						firstChunkIndex: 42,
						lastChunkIndex: 107,
					},
				])
			})

			test('last chunk partially in several lines', () => {
				lines.push({
					firstChunkIndex: 23,
					lastChunkIndex: 42,
				})
				lines.push({
					firstChunkIndex: 42,
					lastChunkIndex: 108,
				})
				lines.push({
					firstChunkIndex: 108,
					lastChunkIndex: 108,
				})
				lines.push({
					firstChunkIndex: 108,
					lastChunkIndex: 108,
				})

				wrapper.vm.removeLastChunkFromLines()

				expect(lines).toEqual([
					{
						firstChunkIndex: 23,
						lastChunkIndex: 42,
					},
					{
						firstChunkIndex: 42,
						lastChunkIndex: 107,
					},
				])
			})
		})
	})

	describe('update lines without layout', () => {
		let wrapper
		let lines
		let getClientRects

		beforeEach(() => {
			getClientRects = vi.spyOn(HTMLElement.prototype, 'getClientRects').mockReturnValue([])

			const chunks = [
				{ message: 'first', languageId: 'en', final: true },
				{ message: 'second', languageId: 'en', final: true },
				{ message: 'third', languageId: 'en', final: false },
			]
			wrapper = shallowMount(TranscriptBlock, { props: { ...props, chunks } })

			lines = wrapper.vm.$data.lines
		})

		afterEach(() => {
			vi.restoreAllMocks()
		})

		test('hidden from the start', () => {
			wrapper.vm.updateLines()

			expect(lines.length).toBe(0)
		})

		test('hidden after lines were detected', () => {
			getClientRects.mockReturnValue([{ top: 0, bottom: 10 }])
			wrapper.vm.updateLines()
			getClientRects.mockReturnValue([])

			wrapper.vm.updateLines()

			expect(lines).toEqual([
				{
					firstChunkIndex: 0,
					lastChunkIndex: 1,
				},
			])
		})
	})
})
