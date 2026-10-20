/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, test } from 'vitest'
import DefaultParameter from './DefaultParameter.vue'
import ImagePreview from './ImagePreview.vue'

describe('ImagePreview.vue', () => {
	const props = {
		type: 'highlight',
		id: 'matrix-media/55',
		name: 'cat.jpg',
		link: 'https://cloud.example/matrix/media/55',
		thumb: 'https://cloud.example/matrix/media/55/preview',
	}

	test('shows the thumbnail linking to the file', () => {
		const wrapper = mount(ImagePreview, { props })

		expect(wrapper.find('a').attributes('href')).toBe(props.link)
		expect(wrapper.find('img').attributes('src')).toBe(props.thumb)
	})

	test('falls back to a link when the thumbnail fails', async () => {
		const wrapper = mount(ImagePreview, { props })

		await wrapper.find('img').trigger('error')

		expect(wrapper.find('img').exists()).toBe(false)
		expect(wrapper.findComponent(DefaultParameter).props('link')).toBe(props.link)
	})
})
