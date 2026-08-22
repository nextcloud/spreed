/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import StripeControls from './StripeControls.vue'

describe('StripeControls.vue', () => {
	const SHOWN_SLOT = 'stripe-controls__slot--shown'

	/**
	 * @param props - the props to override
	 */
	function mountControls(props = {}) {
		return mount(StripeControls, {
			props: {
				currentPage: 0,
				numberOfPages: 1,
				hasPreviousPage: false,
				hasNextPage: false,
				isOpen: true,
				...props,
			},
		})
	}

	/**
	 * The slots shown while the controls are not hovered, in rendering order.
	 *
	 * @param wrapper - the mounted controls
	 */
	function shownSlots(wrapper: ReturnType<typeof mountControls>) {
		return wrapper.findAll('.stripe-controls__slot')
			.map((slot) => slot.classes(SHOWN_SLOT))
	}

	describe('without pagination', () => {
		test('holds the collapse control alone, which is shown on its own', () => {
			const wrapper = mountControls()

			expect(shownSlots(wrapper)).toEqual([true])
			expect(wrapper.find('.stripe-controls__page').exists()).toBe(false)
			expect(wrapper.find('.stripe-controls__separator').exists()).toBe(false)
		})

		test('is not shown until the call is hovered', () => {
			expect(mountControls().classes('stripe-controls--shown')).toBe(false)
		})

		test('shows the expand control of a collapsed stripe on its own', () => {
			// A collapsed stripe shows no tile, so it is never paginated
			const wrapper = mountControls({ isOpen: false, numberOfPages: 3, hasNextPage: true })

			expect(wrapper.classes('stripe-controls--shown')).toBe(true)
			expect(shownSlots(wrapper)).toEqual([true])
		})
	})

	describe('with pagination', () => {
		const paginated = { numberOfPages: 3, hasPreviousPage: true, hasNextPage: true, currentPage: 1 }

		test('is shown to tell that there are more participants', () => {
			expect(mountControls(paginated).classes('stripe-controls--shown')).toBe(true)
		})

		test('shows the arrows of the pages there are, and the rest once hovered', () => {
			// Previous, page indicator, next, separator and collapse
			expect(shownSlots(mountControls(paginated))).toEqual([true, false, true, false, false])
		})

		test('only shows the arrow of the page there is', () => {
			expect(shownSlots(mountControls({ ...paginated, currentPage: 0, hasPreviousPage: false })))
				.toEqual([false, false, true, false, false])
			expect(shownSlots(mountControls({ ...paginated, currentPage: 2, hasNextPage: false })))
				.toEqual([true, false, false, false, false])
		})

		test('indicates the current page', () => {
			expect(mountControls(paginated).find('.stripe-controls__page').text()).toBe('2/3')
		})

		test('emits the page to move to and the collapse', async () => {
			const wrapper = mountControls(paginated)
			const buttons = wrapper.findAll('button')

			await buttons[0].trigger('click')
			await buttons[1].trigger('click')
			await buttons[2].trigger('click')

			expect(wrapper.emitted('previous')).toHaveLength(1)
			expect(wrapper.emitted('next')).toHaveLength(1)
			expect(wrapper.emitted('toggle')).toHaveLength(1)
		})
	})
})
