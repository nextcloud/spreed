/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
	disableBotForConversation,
	enableBotForConversation,
	getConversationBots,
} from '../../services/botsService.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { useBotsStore } from '../bots.ts'

vi.mock('../../services/botsService.ts', () => ({
	getConversationBots: vi.fn(),
	disableBotForConversation: vi.fn(),
	enableBotForConversation: vi.fn(),
}))

describe('botsStore', () => {
	const token = 'TOKEN'
	const bots = [
		{ id: 1, name: 'Dummy bot 1', state: 0 },
		{ id: 2, name: 'Dummy bot 2', state: 1 },
	]
	let botsStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		botsStore = useBotsStore()
	})

	afterEach(async () => {
		vi.clearAllMocks()
	})

	it('returns an empty array when bots are not loaded yet for conversation', async () => {
		// Assert: check initial state of the store
		expect(botsStore.getConversationBots(token)).toEqual([])
	})

	it('processes a response from server and stores bots', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: bots })
		getConversationBots.mockResolvedValueOnce(response)

		// Act: load bots from server
		await botsStore.loadConversationBots(token)

		// Assert
		expect(getConversationBots).toHaveBeenCalledWith(token)
		expect(botsStore.getConversationBots(token)).toEqual(bots)
	})

	it('updates bots in the store after new response from server', async () => {
		// Arrange
		const newBot = { id: 3, name: 'Dummy bot 3', state: 2 }
		const responseFirst = generateOCSResponse({ payload: bots })
		const responseSecond = generateOCSResponse({ payload: [...bots, newBot] })
		getConversationBots
			.mockResolvedValueOnce(responseFirst)
			.mockResolvedValueOnce(responseSecond)

		// Act: load bots from server twice
		await botsStore.loadConversationBots(token)
		await botsStore.loadConversationBots(token)

		// Assert
		expect(getConversationBots).toHaveBeenCalledTimes(2)
		expect(botsStore.getConversationBots(token)).toEqual([...bots, newBot])
	})

	it('toggles bots state according to their current state', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: bots })
		getConversationBots.mockResolvedValueOnce(response)
		await botsStore.loadConversationBots(token)

		const enabledBot = { ...bots[0], state: 1 }
		const responseEnabled = generateOCSResponse({ payload: enabledBot })
		enableBotForConversation.mockResolvedValueOnce(responseEnabled)
		const disabledBot = { ...bots[1], state: 0 }
		const responseDisabled = generateOCSResponse({ payload: disabledBot })
		disableBotForConversation.mockResolvedValueOnce(responseDisabled)

		// Act: enable bot with id 1, disable bot with id 2
		await botsStore.toggleBotState(token, bots[0])
		await botsStore.toggleBotState(token, bots[1])

		// Assert
		expect(enableBotForConversation).toHaveBeenCalledWith(token, bots[0].id)
		expect(disableBotForConversation).toHaveBeenCalledWith(token, bots[1].id)
		expect(botsStore.getConversationBots(token)).toEqual([enabledBot, disabledBot])
	})
})
