/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PollEditor from './PollEditor.vue'

const mocks = vi.hoisted(() => ({
	visibility: true,
	createPoll: vi.fn(),
	updatePollDraft: vi.fn(),
	draft: {
		question: 'Lunch?',
		options: ['Pizza', 'Pasta'],
		resultMode: 14,
		maxVotes: 1,
	},
}))

vi.mock('vuex', () => ({ useStore: () => ({ getters: { conversation: () => ({ displayName: 'Conversation' }) } }) }))
vi.mock('../../composables/useGetThreadId.ts', () => ({ useGetThreadId: () => ref(undefined) }))
vi.mock('../../composables/useGetToken.ts', () => ({ useGetToken: () => ref('room') }))
vi.mock('../../services/CapabilitiesManager.ts', () => ({
	hasTalkFeature: (_token: string, feature: string) => {
		return feature !== 'talk-polls-result-visibility' || mocks.visibility
	},
}))
vi.mock('../../stores/polls.ts', () => ({
	usePollsStore: () => ({
		createPoll: mocks.createPoll,
		updatePollDraft: mocks.updatePollDraft,
		drafts: { room: { 1: mocks.draft } },
	}),
}))

function mountEditor() {
	return shallowMount(PollEditor, {
		props: { token: 'room', canCreatePollDrafts: true },
		global: {
			renderStubDefaultSlot: true,
			stubs: { NcDialog: { template: '<div><slot /><slot name="actions" /></div>' } },
		},
	})
}

describe('PollEditor', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mocks.visibility = true
		mocks.draft.resultMode = 14
	})

	it.each([6, 2, 0, 1])('submits name visibility %i with an independent result preview', async (mode) => {
		const wrapper = mountEditor()
		const select = wrapper.findComponent(NcSelect)
		const selectProps = select.props() as { modelValue: { value: number }, options: { label: string, value: number }[] }
		expect(selectProps.modelValue).toEqual(expect.objectContaining({ value: 0 }))
		expect(selectProps.options.map((option) => option.label)).toEqual(['Always', 'After voting', 'After the poll ends', 'Never'])
		select.vm.$emit('update:modelValue', selectProps.options.find((option) => option.value === mode))
		wrapper.findAllComponents(NcCheckboxRadioSwitch)[0].vm.$emit('update:modelValue', true)
		const fields = wrapper.findAllComponents(NcTextField)
		fields[0].vm.$emit('update:modelValue', 'Lunch?')
		fields[1].vm.$emit('update:modelValue', 'Pizza')
		fields[2].vm.$emit('update:modelValue', 'Pasta')
		await flushPromises()
		wrapper.findAllComponents(NcButton).find((button) => button.text() === 'Create poll')!.vm.$emit('click')
		await flushPromises()
		expect(mocks.createPoll).toHaveBeenCalledWith(expect.objectContaining({ form: expect.objectContaining({ resultMode: mode | 8 }) }))
	})

	it.each([0, 1, 2, 6, 8, 9, 10, 14])('restores and saves draft resultMode %i', async (mode) => {
		mocks.draft.resultMode = mode
		const wrapper = mountEditor()
		wrapper.vm.fillPollEditorFromDraft(1, true, 'edit')
		await flushPromises()
		wrapper.findAllComponents(NcButton).find((button) => button.text() === 'Save')!.vm.$emit('click')
		await flushPromises()
		expect(mocks.updatePollDraft).toHaveBeenCalledWith(expect.objectContaining({ pollId: 1, form: expect.objectContaining({ resultMode: mode }) }))
	})

	it('uses the legacy checkbox on servers without the capability', () => {
		mocks.visibility = false
		const wrapper = mountEditor()
		expect(wrapper.findComponent(NcSelect).exists()).toBe(false)
		expect(wrapper.findAllComponents(NcCheckboxRadioSwitch)).toHaveLength(2)
	})

	it('enables result previews automatically for always-visible names without changing other selections', async () => {
		const wrapper = mountEditor()
		const select = wrapper.findComponent(NcSelect)
		const options = (select.props() as { options: { label: string, value: number }[] }).options
		select.vm.$emit('update:modelValue', options[0])
		await flushPromises()
		const preview = wrapper.findAllComponents(NcCheckboxRadioSwitch)[0]
		expect(preview.props('modelValue')).toBe(true)
		expect(preview.props('disabled')).toBe(true)
		select.vm.$emit('update:modelValue', options[1])
		await flushPromises()
		expect(preview.props('modelValue')).toBe(false)
		expect(preview.props('disabled')).toBe(false)
	})

	it.each([14, 9])('downgrades imported draft mode %i safely on an older server', async (mode) => {
		mocks.visibility = false
		mocks.draft.resultMode = mode
		const wrapper = mountEditor()
		wrapper.vm.fillPollEditorFromDraft(1, true, 'edit')
		await flushPromises()
		wrapper.findAllComponents(NcButton).find((button) => button.text() === 'Save')!.vm.$emit('click')
		await flushPromises()
		expect(mocks.updatePollDraft).toHaveBeenCalledWith(expect.objectContaining({ form: expect.objectContaining({ resultMode: mode & 1 }) }))
	})
})
