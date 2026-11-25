/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import NewConversationTeamPage from './NewConversationTeamPage.vue'
import { getTeams } from '../../services/teamsService.ts'

vi.mock('../../services/teamsService.ts', () => ({
	getTeams: vi.fn(),
}))

const TEAMS = [
	{ id: 'team2', displayName: 'Zebra', description: '', config: 8 },
	{ id: 'team1', displayName: 'Alpha', description: 'First team', config: 0 },
	{ id: 'team3', displayName: 'Managed', description: '', config: 262144 },
]

/**
 * @param {object|null} selectedTeam the selected team
 */
async function mountPage(selectedTeam = null) {
	getTeams.mockResolvedValue({ data: { ocs: { data: TEAMS } } })
	const wrapper = shallowMount(NewConversationTeamPage, {
		props: { selectedTeam },
		global: {
			stubs: {
				NcListItem: {
					name: 'NcListItem',
					props: ['name', 'active'],
					emits: ['click'],
					template: '<li :class="{ active }" @click="$emit(\'click\')">{{ name }}<slot name="subname" /></li>',
				},
			},
		},
	})
	await flushPromises()
	return wrapper
}

describe('NewConversationTeamPage.vue', () => {
	it('lists the teams of the user sorted by name, without teams that can not have a conversation', async () => {
		const wrapper = await mountPage()
		const items = wrapper.findAll('li')
		// Exactly these two: 'Managed' is left out
		expect(items.map((item) => item.text())).toEqual([
			expect.stringContaining('Alpha'),
			expect.stringContaining('Zebra'),
		])
		expect(items[0].text()).toContain('First team')
		// Without description only the name, no new string for the member count
		expect(items[1].text()).toBe('Zebra')
	})

	it('selects a team', async () => {
		const wrapper = await mountPage()
		await wrapper.findAll('li')[1].trigger('click')
		expect(wrapper.emitted('update:selectedTeam')[0][0]).toMatchObject({ id: 'team2' })
	})

	it('marks the selected team', async () => {
		const wrapper = await mountPage(TEAMS[0])
		expect(wrapper.findAll('li.active').map((item) => item.text())).toEqual([expect.stringContaining('Zebra')])
	})

	it('filters teams by name', async () => {
		const wrapper = await mountPage()
		wrapper.vm.searchText = 'alp'
		await wrapper.vm.$nextTick()
		expect(wrapper.findAll('li')).toHaveLength(1)
	})
})
