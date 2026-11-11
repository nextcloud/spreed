/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PollEditor from './PollEditor.vue'

const mocks = vi.hoisted(() => ({
	liveVoters: true,
	resultPreview: true,
	createPoll: vi.fn(),
	updatePollDraft: vi.fn(),
	draft: {
		question: 'Lunch?',
		options: ['Pizza', 'Pasta'],
		resultMode: 0,
		maxVotes: 1,
		showVotersBeforeClosing: true,
		showResultsBeforeVoting: true,
	},
}))

vi.mock('vuex', () => ({ useStore: () => ({ getters: { conversation: () => ({ displayName: 'Conversation' }) } }) }))
vi.mock('../../composables/useGetThreadId.ts', () => ({ useGetThreadId: () => ref(undefined) }))
vi.mock('../../composables/useGetToken.ts', () => ({ useGetToken: () => ref('room') }))
vi.mock('../../services/CapabilitiesManager.ts', () => ({
	hasTalkFeature: (_token: string, feature: string) => {
		if (feature === 'talk-polls-live-voters') {
			return mocks.liveVoters
		}
		return feature !== 'talk-polls-results-before-voting' || mocks.resultPreview
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
		mocks.liveVoters = true
		mocks.resultPreview = true
	})

	it('leaves live voters disabled by default and submits the selected setting', async () => {
		const wrapper = mountEditor()
		const checkboxes = wrapper.findAllComponents(NcCheckboxRadioSwitch)
		expect(checkboxes[1].props('modelValue')).toBe(false)
		checkboxes[1].vm.$emit('update:modelValue', true)
		checkboxes[2].vm.$emit('update:modelValue', true)
		const fields = wrapper.findAllComponents(NcTextField)
		fields[0].vm.$emit('update:modelValue', 'Lunch?')
		fields[1].vm.$emit('update:modelValue', 'Pizza')
		fields[2].vm.$emit('update:modelValue', 'Pasta')
		await flushPromises()
		wrapper.findAllComponents(NcButton).find((button) => button.text() === 'Create poll')!.vm.$emit('click')
		await flushPromises()
		expect(mocks.createPoll).toHaveBeenCalledWith(expect.objectContaining({
			form: expect.objectContaining({ showVotersBeforeClosing: true, showResultsBeforeVoting: true, resultMode: 0 }),
		}))
	})

	it('clears and disables live voters when names are hidden', async () => {
		const wrapper = mountEditor()
		const checkboxes = wrapper.findAllComponents(NcCheckboxRadioSwitch)
		checkboxes[1].vm.$emit('update:modelValue', true)
		checkboxes[0].vm.$emit('update:modelValue', false)
		await flushPromises()
		expect(checkboxes[1].props('modelValue')).toBe(false)
		expect(checkboxes[1].props('disabled')).toBe(true)
	})

	it('restores live voters when editing a draft', async () => {
		const wrapper = mountEditor()
		wrapper.vm.fillPollEditorFromDraft(1, true, 'edit')
		await flushPromises()
		expect(wrapper.findAllComponents(NcCheckboxRadioSwitch)[1].props('modelValue')).toBe(true)
		expect(wrapper.findAllComponents(NcCheckboxRadioSwitch)[2].props('modelValue')).toBe(true)
		wrapper.findAllComponents(NcButton).find((button) => button.text() === 'Save')!.vm.$emit('click')
		await flushPromises()
		expect(mocks.updatePollDraft).toHaveBeenCalledWith(expect.objectContaining({
			pollId: 1,
			form: expect.objectContaining({ showVotersBeforeClosing: true, showResultsBeforeVoting: true }),
		}))
	})

	it('hides the new setting on servers without support', () => {
		mocks.liveVoters = false
		mocks.resultPreview = false
		const wrapper = mountEditor()
		expect(wrapper.findAllComponents(NcCheckboxRadioSwitch)).toHaveLength(2)
	})

	it('keeps result previews available when names are hidden', async () => {
		const wrapper = mountEditor()
		const checkboxes = wrapper.findAllComponents(NcCheckboxRadioSwitch)
		expect(checkboxes[2].props('modelValue')).toBe(false)
		checkboxes[2].vm.$emit('update:modelValue', true)
		checkboxes[0].vm.$emit('update:modelValue', false)
		await flushPromises()
		expect(checkboxes[2].props('disabled')).toBe(false)
		expect(checkboxes[2].props('modelValue')).toBe(true)
	})
})
