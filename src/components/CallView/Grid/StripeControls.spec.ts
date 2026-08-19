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
		test('holds the collapse control alone', () => {
			const wrapper = mountControls()

			expect(wrapper.findAll('.stripe-controls__group')).toHaveLength(1)
			expect(shownSlots(wrapper)).toEqual([])
			expect(wrapper.find('.stripe-controls__page').exists()).toBe(false)
		})

		test('holds the expand control alone when the stripe is collapsed', () => {
			// A collapsed stripe shows no tile, so it is never paginated
			const wrapper = mountControls({ isOpen: false, numberOfPages: 3, hasNextPage: true })

			expect(wrapper.findAll('.stripe-controls__group')).toHaveLength(1)
			expect(wrapper.find('.stripe-controls__page').exists()).toBe(false)
		})
	})

	describe('with pagination', () => {
		const paginated = { numberOfPages: 3, hasPreviousPage: true, hasNextPage: true, currentPage: 1 }

		test('holds the paging controls next to the collapse control', () => {
			expect(mountControls(paginated).findAll('.stripe-controls__group')).toHaveLength(2)
		})

		test('shows the arrows of the pages there are, and the page it is on once hovered', () => {
			// Previous, page indicator and next
			expect(shownSlots(mountControls(paginated))).toEqual([true, false, true])
		})

		test('only shows the arrow of the page there is', () => {
			expect(shownSlots(mountControls({ ...paginated, currentPage: 0, hasPreviousPage: false })))
				.toEqual([false, false, true])
			expect(shownSlots(mountControls({ ...paginated, currentPage: 2, hasNextPage: false })))
				.toEqual([true, false, false])
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
