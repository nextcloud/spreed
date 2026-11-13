/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import SharedItems from './SharedItems.vue'
import { SHARED_ITEM } from '../../../constants.ts'

describe('SharedItems.vue', () => {
	const items = {
		1: { id: 1, message: 'https://a1.tld https://a2.tld' },
		2: { id: 2, message: 'https://b1.tld https://b2.tld https://b3.tld https://b4.tld' },
	}

	beforeEach(() => {
		setActivePinia(createPinia())
	})

	/**
	 * @param {number} [limit] limit of displayed links
	 */
	function getRenderedLinks(limit) {
		const wrapper = mount(SharedItems, {
			props: { token: 'XXTOKENXX', type: SHARED_ITEM.TYPES.LINK, items, limit },
		})
		return wrapper.findAll('a').map((link) => link.attributes('href'))
	}

	it('limits the number of links, newest messages first', () => {
		expect(getRenderedLinks(5)).toEqual(['https://b1.tld', 'https://b2.tld', 'https://b3.tld', 'https://b4.tld', 'https://a1.tld'])
	})

	it('renders all links without limit', () => {
		expect(getRenderedLinks()).toHaveLength(6)
	})
})
