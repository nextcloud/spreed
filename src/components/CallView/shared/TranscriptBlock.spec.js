/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, test } from 'vitest'
import TranscriptBlock from './TranscriptBlock.vue'

describe('TranscriptBlock.vue', () => {
	describe('remove last chunk from lines', () => {
		let wrapper
		let lines

		beforeEach(() => {
			wrapper = shallowMount(TranscriptBlock, {
				props: {
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
				},
			})

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
})
