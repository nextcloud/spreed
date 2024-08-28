/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'

import { getShares, acceptShare, rejectShare } from '../../services/federationService.ts'
import { generateOCSErrorResponse, generateOCSResponse } from '../../test-helpers.js'
import { useFederationStore } from '../federation.ts'

jest.mock('../../services/federationService', () => ({
	getShares: jest.fn(),
	acceptShare: jest.fn(),
	rejectShare: jest.fn(),
}))

describe('federationStore', () => {
	const invites = [
		{
			id: 2,
			userId: 'user0',
			state: 0,
			localToken: 'TOKEN_LOCAL_2',
			remoteServerUrl: 'remote.nextcloud.com',
			remoteToken: 'TOKEN_2',
			remoteAttendeeId: 11,
			inviterCloudId: 'user2@remote.nextcloud.com',
			inviterDisplayName: 'User Two',
			roomName: 'Federation room 2'
		},
		{
			id: 1,
			userId: 'user0',
			state: 1,
			localToken: 'TOKEN_LOCAL_1',
			remoteServerUrl: 'remote.nextcloud.com',
			remoteToken: 'TOKEN_1',
			remoteAttendeeId: 11,
			inviterCloudId: 'user1@remote.nextcloud.com',
			inviterDisplayName: 'User One',
			roomName: 'Federation room 1'
		},
	]
	const notifications = [
		{
			notificationId: 122,
			app: 'spreed',
			user: 'user0',
			objectType: 'remote_talk_share',
			objectId: '2',
			messageRichParameters: {
				user1: {
					type: 'user',
					id: 'user2',
					name: 'User Two',
					server: 'remote.nextcloud.com'
				},
				roomName: {
					type: 'highlight',
					id: 'remote.nextcloud.com::TOKEN_2',
					name: 'Federation room 2'
				},
			},
		},
		{
			notificationId: 123,
			app: 'spreed',
			user: 'user0',
			objectType: 'remote_talk_share',
			objectId: '3',
			messageRichParameters: {
				user1: {
					type: 'user',
					id: 'user3',
					name: 'User Three',
					server: 'remote.nextcloud.com'
				},
				roomName: {
					type: 'highlight',
					id: 'remote.nextcloud.com::TOKEN_3',
					name: 'Federation room 3'
				},
			},
		}
	]
	let federationStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		federationStore = useFederationStore()
	})

	afterEach(async () => {
		jest.clearAllMocks()
	})

	it('returns an empty objects when invitations are not loaded yet', async () => {
		// Assert: check initial state of the store
		expect(federationStore.pendingShares).toStrictEqual({})
		expect(federationStore.acceptedShares).toStrictEqual({})
	})

	it('does not handle accepted invitations when missing in the store', async () => {
		// Act: accept invite from notification
		await federationStore.markInvitationAccepted(invites[0].id, {})

		// Assert: check initial state of the store
		expect(federationStore.pendingShares).toStrictEqual({})
		expect(federationStore.acceptedShares).toStrictEqual({})
	})

	it('processes a response from server and stores invites', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)

		// Act: load invites from server
		await federationStore.getShares()

		// Assert
		expect(getShares).toHaveBeenCalled()
		expect(federationStore.pendingShares).toMatchObject({ [invites[0].id]: invites[0] })
		expect(federationStore.acceptedShares).toMatchObject({ [invites[1].id]: invites[1] })
		expect(federationStore.pendingSharesCount).toBe(1)
	})

	it('processes a response from server and remove outdated invites', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()

		// Act: load empty response from server
		const responseEmpty = generateOCSResponse({ payload: [] })
		getShares.mockResolvedValueOnce(responseEmpty)
		await federationStore.getShares()

		// Assert
		expect(federationStore.pendingShares).toStrictEqual({})
		expect(federationStore.acceptedShares).toStrictEqual({})
		expect(federationStore.pendingSharesCount).toBe(0)
	})

	it('handles error in server request for getShares', async () => {
		// Arrange
		const response = generateOCSErrorResponse({ status: 404, payload: [] })
		getShares.mockRejectedValueOnce(response)
		console.error = jest.fn()

		// Act
		await federationStore.getShares()

		// Assert: store hasn't changed
		expect(federationStore.pendingShares).toStrictEqual({})
		expect(federationStore.acceptedShares).toStrictEqual({})
		expect(federationStore.pendingSharesCount).toBe(0)
	})

	it('updates invites in the store after receiving a notification', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()

		// Act: trigger notification handling
		notifications.forEach(federationStore.addInvitationFromNotification)

		// Assert
		expect(federationStore.pendingShares).toMatchObject({
			[invites[0].id]: invites[0],
			[notifications[1].objectId]: { id: +notifications[1].objectId },
		})
		expect(federationStore.acceptedShares).toMatchObject({ [invites[1].id]: invites[1] })
		expect(federationStore.pendingSharesCount).toBe(2)
	})

	it('accepts invitation and modify store', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()

		const room = {
			id: 10,
			token: 'TOKEN_LOCAL_2'
		}
		const acceptResponse = generateOCSResponse({ payload: room })
		acceptShare.mockResolvedValueOnce(acceptResponse)

		// Act: accept invite
		const conversation = await federationStore.acceptShare(invites[0].id)

		// Assert
		expect(acceptShare).toHaveBeenCalledWith(invites[0].id)
		expect(conversation).toMatchObject(room)
		expect(federationStore.pendingShares).toStrictEqual({})
		expect(federationStore.acceptedShares).toMatchObject({
			[invites[0].id]: { ...invites[0], state: 1 },
			[invites[1].id]: invites[1],
		})
		expect(federationStore.pendingSharesCount).toBe(0)
	})

	it('skip already accepted invitations', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()

		// Act: accept invite
		await federationStore.acceptShare(invites[1].id)

		// Assert
		expect(acceptShare).not.toHaveBeenCalled()
		expect(federationStore.pendingShares).toMatchObject({ [invites[0].id]: invites[0] })
		expect(federationStore.acceptedShares).toMatchObject({ [invites[1].id]: invites[1] })
	})

	it('handles error in server request for acceptShare', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()
		const errorResponse = generateOCSErrorResponse({ status: 404, payload: [] })
		acceptShare.mockRejectedValueOnce(errorResponse)
		console.error = jest.fn()

		// Act
		await federationStore.acceptShare(invites[0].id)

		// Assert: store hasn't changed
		expect(federationStore.pendingShares).toMatchObject({ [invites[0].id]: invites[0] })
		expect(federationStore.acceptedShares).toMatchObject({ [invites[1].id]: invites[1] })
	})

	it('rejects invitation and modify store', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()

		const rejectResponse = generateOCSResponse({ payload: [] })
		rejectShare.mockResolvedValueOnce(rejectResponse)

		// Act: reject invite
		await federationStore.rejectShare(invites[0].id)

		// Assert
		expect(rejectShare).toHaveBeenCalledWith(invites[0].id)
		expect(federationStore.pendingShares).toStrictEqual({})
		expect(federationStore.acceptedShares).toMatchObject({ [invites[1].id]: invites[1] })
		expect(federationStore.pendingSharesCount).toBe(0)
	})

	it('skip already rejected invitations', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()

		// Act: reject invite
		await federationStore.rejectShare(invites[1].id)

		// Assert
		expect(rejectShare).not.toHaveBeenCalled()
		expect(federationStore.pendingShares).toMatchObject({ [invites[0].id]: invites[0] })
		expect(federationStore.acceptedShares).toMatchObject({ [invites[1].id]: invites[1] })
	})

	it('handles error in server request for rejectShare', async () => {
		// Arrange
		const response = generateOCSResponse({ payload: invites })
		getShares.mockResolvedValueOnce(response)
		await federationStore.getShares()
		const errorResponse = generateOCSErrorResponse({ status: 404, payload: [] })
		rejectShare.mockRejectedValueOnce(errorResponse)
		console.error = jest.fn()

		// Act
		await federationStore.rejectShare(invites[0].id)

		// Assert: store hasn't changed
		expect(federationStore.pendingShares).toMatchObject({ [invites[0].id]: invites[0] })
		expect(federationStore.acceptedShares).toMatchObject({ [invites[1].id]: invites[1] })
	})
})
