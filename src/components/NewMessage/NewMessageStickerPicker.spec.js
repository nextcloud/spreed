/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import NewMessageStickerPicker from './NewMessageStickerPicker.vue'
import { useStickersStore } from '../../stores/stickers.ts'

const NcPopover = {
	name: 'NcPopover',
	props: ['shown'],
	emits: ['update:shown'],
	template: '<div><slot name="trigger" /><slot /></div>',
}

describe('NewMessageStickerPicker.vue', () => {
	const packs = [
		{
			id: 10,
			name: 'Cats',
			stickers: [
				{ id: 11, name: 'angry', path: '/Stickers/Cats/angry.png', mimetype: 'image/png', etag: 'e11' },
				{ id: 12, name: 'sleepy', path: '/Stickers/Cats/sleepy.gif', mimetype: 'image/gif', etag: 'e12' },
			],
		},
		{
			id: 20,
			name: 'Dogs',
			stickers: [{ id: 21, name: 'wag', path: '/Stickers/Dogs/wag.webp', mimetype: 'image/webp', etag: 'e21' }],
		},
	]
	let stickersStore

	beforeEach(() => {
		setActivePinia(createPinia())
		stickersStore = useStickersStore()
		stickersStore.fetchPacks = vi.fn()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	/**
	 * Mount the picker as opened
	 */
	async function mountOpened() {
		const wrapper = mount(NewMessageStickerPicker, {
			props: { folder: 'Stickers' },
			global: { stubs: { NcPopover } },
		})
		await wrapper.findComponent(NcPopover).vm.$emit('update:shown', true)
		return wrapper
	}

	it('fetches the packs when opened', async () => {
		await mountOpened()

		expect(stickersStore.fetchPacks).toHaveBeenCalledTimes(1)
	})

	it('names the folder when there are no stickers', async () => {
		stickersStore.loaded = true

		const wrapper = await mountOpened()

		expect(wrapper.text()).toContain('No stickers')
		expect(wrapper.text()).toContain('Stickers')
	})

	it('shows the first pack and switches to another one', async () => {
		stickersStore.packs = packs
		stickersStore.loaded = true

		const wrapper = await mountOpened()

		expect(wrapper.findAll('.sticker-picker__sticker').map((button) => button.attributes('aria-label'))).toEqual(['angry', 'sleepy'])
		expect(wrapper.find('img').attributes('src')).toContain('fileId=11')

		await wrapper.findAll('[role="tab"]')[1].trigger('click')

		expect(wrapper.findAll('.sticker-picker__sticker').map((button) => button.attributes('aria-label'))).toEqual(['wag'])
	})

	it('emits the selected sticker and closes', async () => {
		stickersStore.packs = packs
		stickersStore.loaded = true

		const wrapper = await mountOpened()
		await wrapper.findAll('.sticker-picker__sticker')[1].trigger('click')

		expect(wrapper.emitted('select')).toEqual([[packs[0].stickers[1]]])
		expect(wrapper.findComponent(NcPopover).props('shown')).toBe(false)
	})
})
