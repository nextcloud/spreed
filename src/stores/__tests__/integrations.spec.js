/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'

import { useIntegrationsStore } from '../integrations.js'

describe('integrationsStore', () => {
	let integrationsStore

	beforeEach(() => {
		setActivePinia(createPinia())
		integrationsStore = useIntegrationsStore()
	})

	afterEach(async () => {
		jest.clearAllMocks()
	})

	describe('message actions', () => {
		it('adds and returns message actions', () => {
			// Arrange
			const action = { label: 'first action', icon: 'some-icon', callback: jest.fn() }

			// Act: add action to the store
			integrationsStore.addMessageAction(action)

			// Assert
			expect(integrationsStore.messageActions).toStrictEqual([action])
		})
	})

	describe('participant search', () => {
		it('adds and returns participant search', () => {
			// Arrange
			const action = { label: 'first action', icon: 'some-icon', callback: jest.fn(), show: jest.fn() }

			// Act: update read status and typing status privacy
			integrationsStore.addParticipantSearchAction(action)

			// Assert
			expect(integrationsStore.participantSearchActions).toStrictEqual([action])
		})
	})
})
