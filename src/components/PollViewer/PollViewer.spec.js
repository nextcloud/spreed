/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, shallowMount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PollViewer from './PollViewer.vue'

const mocks = vi.hoisted(() => ({ store: null }))

vi.mock('../../stores/polls.ts', () => ({ usePollsStore: () => mocks.store }))
vi.mock('../../stores/actor.ts', () => ({ useActorStore: () => ({ checkIfSelfIsActor: () => false }) }))
vi.mock('../../composables/useIsInCall.js', () => ({ useIsInCall: () => ref(false) }))
vi.mock('../../services/CapabilitiesManager.ts', () => ({ hasTalkFeature: () => true }))

describe('PollViewer results', () => {
	let poll
	let wrapper

	beforeEach(() => {
		poll = reactive({
			id: 1,
			question: 'Lunch?',
			options: ['Pizza', 'Pasta', 'Salad'],
			resultMode: 0,
			status: 0,
			maxVotes: 1,
			showVotersBeforeClosing: false,
			showResultsBeforeVoting: false,
			votes: { 'option-0': 2, 'option-1': 1 },
			numVoters: 3,
			votedSelf: [],
		})
		mocks.store = {
			activePoll: { token: 'room', id: '1', name: 'Lunch?' },
			getPoll: vi.fn(() => poll),
			getPollData: vi.fn(),
			hidePollToast: vi.fn(),
			submitVote: vi.fn(),
		}
	})

	afterEach(() => {
		wrapper?.unmount()
	})

	function mountViewer() {
		wrapper = shallowMount(PollViewer, {
			global: {
				renderStubDefaultSlot: true,
				stubs: { NcModal: { template: '<div><slot /></div>' } },
				mocks: { $store: { getters: { isModerator: false } } },
			},
		})
		return wrapper
	}

	it.each([0, 1])('shows counts and percentages before voting in mode %i when enabled', (mode) => {
		poll.resultMode = mode
		poll.showResultsBeforeVoting = true
		mountViewer()
		expect(wrapper.find('.poll-modal__options').exists()).toBe(true)
		expect(wrapper.findAll('.percentage').map((item) => item.text())).toEqual(['2 votes (67%)', '1 vote (33%)', '0 votes (0%)'])
	})

	it.each([0, 1])('does not show results before voting in mode %i by default', (mode) => {
		poll.resultMode = mode
		mountViewer()
		expect(wrapper.find('.results__options').exists()).toBe(false)
	})

	it.each([0, 1])('shows absolute counts in closed polls in mode %i', (mode) => {
		poll.resultMode = mode
		poll.status = 1
		mountViewer()
		expect(wrapper.findAll('.percentage').map((item) => item.text())).toEqual(['2 votes (67%)', '1 vote (33%)', '0 votes (0%)'])
	})

	it('shows absolute counts after voting on a public poll without preview', () => {
		poll.votedSelf = [0]
		mountViewer()
		expect(wrapper.find('.percentage').text()).toBe('2 votes (67%)')
	})

	it('shows zero votes and zero percent for a poll without votes', () => {
		poll.showResultsBeforeVoting = true
		poll.votes = {}
		poll.numVoters = 0
		mountViewer()
		expect(wrapper.findAll('.percentage').map((item) => item.text())).toEqual(['0 votes (0%)', '0 votes (0%)', '0 votes (0%)'])
	})

	it('can submit a vote while the result preview is visible', async () => {
		poll.resultMode = 1
		poll.showResultsBeforeVoting = true
		mountViewer()
		wrapper.findAllComponents(NcCheckboxRadioSwitch)[0].vm.$emit('update:modelValue', '0')
		await flushPromises()
		const submit = wrapper.findAllComponents(NcButton).find((button) => button.text() === 'Submit')
		expect(submit.props('disabled')).toBe(false)
		submit.vm.$emit('click')
		await flushPromises()
		expect(mocks.store.submitVote).toHaveBeenCalledWith({ token: 'room', pollId: '1', optionIds: [0] })
	})

	it('refreshes a cached poll when opening the viewer', () => {
		mountViewer()
		expect(mocks.store.getPollData).toHaveBeenCalledWith({ token: 'room', pollId: '1' })
	})

	it('renders voter details before voting when both options allow it', () => {
		poll.showResultsBeforeVoting = true
		poll.showVotersBeforeClosing = true
		poll.details = [{ actorType: 'users', actorId: 'alice', actorDisplayName: 'Alice', optionId: 0 }]
		mountViewer()
		const details = wrapper.findComponent({ name: 'PollVotersDetails' })
		expect(details.props('details')).toEqual(poll.details)
		expect(wrapper.text()).toContain('You can see who voted for each option before voting')
	})
})
