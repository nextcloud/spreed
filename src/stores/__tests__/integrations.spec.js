/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import { useIntegrationsStore } from '../integrations.js'

describe('integrationsStore', () => {
	let integrationsStore

	beforeEach(() => {
		setActivePinia(createPinia())
		integrationsStore = useIntegrationsStore()
	})

	afterEach(async () => {
		vi.clearAllMocks()
	})

	describe('message actions', () => {
		it('adds and returns message actions', () => {
			// Arrange
			const action = { label: 'first action', icon: 'some-icon', callback: vi.fn() }

			// Act: add action to the store
			integrationsStore.addMessageAction(action)

			// Assert
			expect(integrationsStore.messageActions).toStrictEqual([action])
		})
	})

	describe('participant search', () => {
		it('adds and returns participant search', () => {
			// Arrange
			const action = { label: 'first action', icon: 'some-icon', callback: vi.fn(), show: vi.fn() }

			// Act: update read status and typing status privacy
			integrationsStore.addParticipantSearchAction(action)

			// Assert
			expect(integrationsStore.participantSearchActions).toStrictEqual([action])
		})
	})
})
