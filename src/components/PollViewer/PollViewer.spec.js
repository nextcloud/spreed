/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { createStore } from 'vuex'
import PollViewer from './PollViewer.vue'
import router from '../../__mocks__/router.js'
import { ATTENDEE, POLL } from '../../constants.ts'
import storeConfig from '../../store/storeConfig.js'
import { usePollsStore } from '../../stores/polls.ts'
import { findNcCheckboxRadioSwitch } from '../../test-helpers.js'

describe('PollViewer.vue', () => {
	const TOKEN = 'XXTOKENXX'
	let store
	let pollsStore
	let poll

	beforeEach(() => {
		setActivePinia(createPinia())
		pollsStore = usePollsStore()
		store = createStore(cloneDeep(storeConfig))

		poll = {
			id: 1,
			question: 'What is the answer?',
			options: ['Option A', 'Option B', 'Option C'],
			votes: {},
			numVoters: 0,
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorId: 'bob',
			actorDisplayName: 'Bob',
			status: POLL.STATUS.OPEN,
			resultMode: POLL.MODE.PUBLIC,
			maxVotes: POLL.ANSWER_TYPE.MULTIPLE,
			votedSelf: [],
		}

		pollsStore.addPoll({ token: TOKEN, poll })
		pollsStore.setActivePoll({ token: TOKEN, pollId: poll.id, name: 'Bob' })
	})

	const mountPollViewer = () => {
		return mount(PollViewer, {
			global: {
				plugins: [router, store],
				stubs: {
					NcModal: { template: '<div><slot /></div>' },
				},
			},
		})
	}

	it('keeps selected options when another participant votes', async () => {
		const wrapper = mountPollViewer()
		await nextTick()

		await findNcCheckboxRadioSwitch(wrapper, 'Option A').find('input').setValue(true)
		await findNcCheckboxRadioSwitch(wrapper, 'Option C').find('input').setValue(true)

		pollsStore.addPoll({
			token: TOKEN,
			poll: { ...poll, votes: { 'option-1': 1 }, numVoters: 1 },
		})
		await nextTick()

		expect(findNcCheckboxRadioSwitch(wrapper, 'Option A').find('input').element.checked).toBe(true)
		expect(findNcCheckboxRadioSwitch(wrapper, 'Option B').find('input').element.checked).toBe(false)
		expect(findNcCheckboxRadioSwitch(wrapper, 'Option C').find('input').element.checked).toBe(true)
	})

	it('shows the results when the poll is closed', async () => {
		const wrapper = mountPollViewer()
		await nextTick()

		await findNcCheckboxRadioSwitch(wrapper, 'Option A').find('input').setValue(true)

		pollsStore.addPoll({
			token: TOKEN,
			poll: { ...poll, status: POLL.STATUS.CLOSED },
		})
		await nextTick()

		expect(findNcCheckboxRadioSwitch(wrapper, 'Option A').exists()).toBe(false)
		expect(wrapper.text()).toContain('Final results')
	})
})
