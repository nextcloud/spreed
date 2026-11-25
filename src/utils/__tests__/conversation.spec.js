/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, test } from 'vitest'
import { CONVERSATION } from '../../constants.ts'
import { filterConversation, getTeamPageUrl, isTeamConversation, isTeamError } from '../conversation.ts'

describe('isTeamConversation', () => {
	test('team conversations on this server', () => {
		expect(isTeamConversation({ objectType: CONVERSATION.OBJECT_TYPE.TEAM, attributes: CONVERSATION.ATTRIBUTE.TEAM })).toBe(true)
	})

	test('copies of federated team conversations only have the attribute', () => {
		expect(isTeamConversation({ objectType: '', attributes: CONVERSATION.ATTRIBUTE.TEAM | CONVERSATION.ATTRIBUTE.CHANNEL, remoteServer: 'https://host.example' })).toBe(true)
	})

	test('other conversations', () => {
		expect(isTeamConversation({ objectType: '', attributes: CONVERSATION.ATTRIBUTE.CHANNEL })).toBe(false)
		expect(isTeamConversation(undefined)).toBe(false)
	})
})

describe('filterConversation', () => {
	const team = { objectType: CONVERSATION.OBJECT_TYPE.TEAM, attributes: CONVERSATION.ATTRIBUTE.TEAM, unreadMessages: 2, unreadMention: false }
	const other = { objectType: '', attributes: 0, unreadMessages: 2, unreadMention: false }

	test('team conversations', () => {
		expect(filterConversation(team, ['teams'])).toBe(true)
		expect(filterConversation(other, ['teams'])).toBe(false)
	})

	test('combined with other filters', () => {
		expect(filterConversation(team, ['teams', 'unread'])).toBe(true)
		expect(filterConversation(team, ['teams', 'mentions'])).toBe(false)
	})
})

describe('isTeamError', () => {
	test('refused because of the team', () => {
		expect(isTeamError({ response: { data: { ocs: { data: { error: 'team' } } } } })).toBe(true)
	})

	test('other errors', () => {
		expect(isTeamError({ response: { data: { ocs: { data: { error: 'type' } } } } })).toBe(false)
		expect(isTeamError(new Error('network'))).toBe(false)
		expect(isTeamError(undefined)).toBe(false)
	})
})

describe('getTeamPageUrl', () => {
	const appswebroots = window.OC?.appswebroots

	afterEach(() => {
		window.OC = { ...window.OC, appswebroots }
	})

	test('links to the team when the Teams app is enabled', () => {
		window.OC = { ...window.OC, appswebroots: { ...appswebroots, circles: '/apps/circles' } }
		expect(getTeamPageUrl('abc')).toContain('/apps/circles/teams/team/abc')
	})

	test('empty without the Teams app', () => {
		window.OC = { ...window.OC, appswebroots: {} }
		expect(getTeamPageUrl('abc')).toBe('')
	})
})
