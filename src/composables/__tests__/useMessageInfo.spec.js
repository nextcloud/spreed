/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import { computed, ref } from 'vue'
import { useStore } from 'vuex'
import { ATTENDEE, CONVERSATION, MESSAGE } from '../../constants.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useGuestNameStore } from '../../stores/guestName.js'
import { useConversationInfo } from '../useConversationInfo.ts'
import { useMessageInfo } from '../useMessageInfo.ts'

// Test messages with 'edit-messages' and without 'delete-messages-unlimited' feature
vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		spreed: {
			features: ['edit-messages', 'edit-messages-note-to-self'],
			'features-local': [],
		},
	})),
}))
vi.mock('vuex')
vi.mock('../useConversationInfo.ts')

describe('message actions', () => {
	let message
	let conversationProps
	let mockConversationInfo
	let actorStore
	const TOKEN = 'XXTOKENXX'

	vi.useFakeTimers().setSystemTime(new Date('2024-05-01 17:00:00'))

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()

		actorStore.actorId = 'user-id-1'
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.USERS

		message = ref({
			message: 'test message',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorId: 'user-id-1',
			actorDisplayName: 'user-display-name-1',
			messageParameters: {},
			id: 123,
			isReplyable: true,
			timestamp: new Date('2024-05-01 16:15:00').getTime() / 1000,
			token: TOKEN,
			systemMessage: '',
			messageType: MESSAGE.TYPE.COMMENT,
		})
		conversationProps = {
			token: TOKEN,
			lastCommonReadMessage: 0,
			type: CONVERSATION.TYPE.GROUP,
			readOnly: CONVERSATION.STATE.READ_WRITE,
		}
		useStore.mockReturnValue({
			getters: {
				conversation: () => conversationProps,
				isModerator: false,
			},
		})

		mockConversationInfo = {
			isOneToOneConversation: computed(() => false),
			isConversationReadOnly: computed(() => false),
			isConversationModifiable: computed(() => true),
		}

		useConversationInfo.mockReturnValue(mockConversationInfo)
	})

	test('message is not deleteable when it is older than 6 hours and unlimited capability is disabled', () => {
		// Arrange
		message.value.timestamp = new Date('2024-05-01 7:20:00').getTime() / 1000
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isDeleteable.value).toBe(false)
	})

	test('message is not deleteable when the conversation is read-only', () => {
		// Arrange
		mockConversationInfo = {
			isOneToOneConversation: computed(() => false),
			isConversationReadOnly: computed(() => true),
			isConversationModifiable: computed(() => false),
		}
		useConversationInfo.mockReturnValue(mockConversationInfo)
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isDeleteable.value).toBe(false)
	})

	test('file message is deleteable', () => {
		// Arrange
		message.value.message = '{file}'
		message.value.messageParameters.file = {}
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isFileShare.value).toBe(true)
		expect(result.isFileShareWithoutCaption.value).toBe(true)
		expect(result.isDeleteable.value).toBe(true)
	})

	test('other people messages are not deleteable for non-moderators', () => {
		// Arrange
		message.value.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isDeleteable.value).toBe(false)
	})

	test('other people messages are deleteable for moderators', () => {
		// Arrange
		message.value.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		useStore.mockReturnValue({
			getters: {
				conversation: () => conversationProps,
				isModerator: true,
			},
		})
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.MODERATOR
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isDeleteable.value).toBe(true)
	})

	test('other people message is not deleteable in one to one conversations', () => {
		// Arrange
		message.value.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.ONE_TO_ONE
		useStore.mockReturnValue({
			getters: {
				conversation: () => conversationProps,
				isModerator: false,
			},
		})
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isDeleteable.value).toBe(false)
	})

	test('can edit own message', () => {
		// Arrange
		// capabilities are set
		// the message is owned by the current user
		// the conversation is modifiable (not read-only and user is not a guest or guest moderator)
		// the message is not a object share (e.g. poll)

		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(true)
		expect(result.isEditable.value).toBe(true)
	})

	test('can edit own message in note to self', () => {
		// Arrange
		message.value.timestamp = new Date('2024-04-28 7:20:00').getTime() / 1000
		conversationProps.type = CONVERSATION.TYPE.NOTE_TO_SELF
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(true)
		expect(result.isEditable.value).toBe(true)
	})

	test('moderator can edit other people messages', () => {
		// Arrange
		message.value.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		useStore.mockReturnValue({
			getters: {
				conversation: () => conversationProps,
				isModerator: true,
			},
		})
		actorStore.actorType = ATTENDEE.ACTOR_TYPE.MODERATOR
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isEditable.value).toBe(true)
	})

	test('user can not edit other people messages', () => {
		// Arrange
		message.value.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		useStore.mockReturnValue({
			getters: {
				conversation: () => conversationProps,
				isModerator: false,
			},
		})
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isEditable.value).toBe(false)
	})

	test('system message is not editable', () => {
		// Arrange
		message.value.systemMessage = 'system-message'
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isEditable.value).toBe(false)
	})

	test('return value is reactive', () => {
		// Arrange
		message.value.systemMessage = 'system-message'
		const result = useMessageInfo(message)
		expect(result.isEditable.value).toBe(false)

		// Act
		message.value.systemMessage = ''
		// Assert
		expect(result.isEditable.value).toBe(true)
	})

	test('message is not editable when the conversation is not modifiable', () => {
		// Arrange
		mockConversationInfo = {
			isOneToOneConversation: computed(() => false),
			isConversationReadOnly: computed(() => false),
			isConversationModifiable: computed(() => false),
		}
		useConversationInfo.mockReturnValue(mockConversationInfo)
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.isEditable.value).toBe(false)
	})

	/**
	 * @param {object} message message object
	 */
	function testErrorHandling(message) {
		const result = useMessageInfo(message)
		// Assert
		expect(result.isEditable.value).toBe(false)
		expect(result.isDeleteable.value).toBe(false)
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isObjectShare.value).toBe(false)
		expect(result.isConversationModifiable.value).toBe(false)
		expect(result.isConversationReadOnly.value).toBe(false)
		expect(result.isFileShareWithoutCaption.value).toBe(false)
		expect(result.isFileShare.value).toBe(false)
	}

	test('error handling, return false when conversation is not available', () => {
		// Arrange
		useStore.mockReturnValue({
			getters: {
				conversation: () => undefined,
			},
		})
		// Act
		testErrorHandling(message)
	})

	test('error handling, return false when message is not available', () => {
		// Act
		testErrorHandling(undefined)
	})

	test('return server name for remote server messages', () => {
		// Arrange
		message.value.actorType = ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
		message.value.actorId = 'userid@nc-webroot'
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.remoteServer.value).toBe('(nc-webroot)')
	})

	test('return empty string for local server messages', () => {
		// Arrange
		// actorType remains local user
		message.value.actorId = 'userid@nc-webroot'
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.remoteServer.value).toBe('')
	})

	test('return empty string for messages not edited', () => {
		// Arrange
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.lastEditor.value).toBe('')
	})

	test('return display name', () => {
		// Arrange
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.actorDisplayName.value).toBe('user-display-name-1')
	})

	test('return "Guest" for messages from guests without display name', () => {
		// Arrange
		message.value.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
		message.value.actorDisplayName = ''
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.actorDisplayName.value).toBe('Guest')
	})

	test('return guest name from store for messages from guests', () => {
		// Arrange
		message.value.actorType = ATTENDEE.ACTOR_TYPE.GUESTS
		message.value.actorDisplayName = ''
		message.value.actorId = 'guest-name-1'
		const guestNameStore = useGuestNameStore()
		guestNameStore.addGuestName({
			token: TOKEN,
			actorId: 'guest-name-1',
			actorDisplayName: 'guest name',
		}, { noUpdate: true })

		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.actorDisplayName.value).toBe('guest name')
	})

	test('return "Deleted user" as a fallback for messages from deleted users without display name', () => {
		// Arrange
		message.value.actorDisplayName = ''
		// Act
		const result = useMessageInfo(message)
		// Assert
		expect(result.actorDisplayName.value).toBe('')
		expect(result.actorDisplayNameWithFallback.value).toBe('Deleted user')
	})

	describe('edited messages', () => {
		beforeEach(() => {
			message = ref({
				message: 'test message',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorId: 'user-id-1',
				actorDisplayName: 'user-display-name-1',
				messageParameters: {},
				id: 123,
				isReplyable: true,
				lastEditTimestamp: new Date('2024-05-01 16:30:00').getTime() / 1000,
				lastEditActorId: 'user-id-1',
				lastEditActorType: ATTENDEE.ACTOR_TYPE.USERS,
				lastEditActorDisplayName: 'user-display-name-1',
				timestamp: new Date('2024-05-01 16:15:00').getTime() / 1000,
				token: TOKEN,
				systemMessage: '',
				messageType: MESSAGE.TYPE.COMMENT,
			})
		})
		test('return last editor when message is edited', () => {
			// Arrange
			// Act
			const result = useMessageInfo(message)
			// Assert
			expect(result.lastEditor.value).toBe('(edited)')
		})

		test('includes editor name when they are not the author of the message', () => {
			// Arrange
			message.value.lastEditActorId = 'user-id-2'
			message.value.lastEditActorDisplayName = 'user-display-name-2'
			// Act
			const result = useMessageInfo(message)
			// Assert
			expect(result.lastEditor.value).toBe('(edited by user-display-name-2)')
		})

		test('return "edited by a deleted user" when editor a deleted user', () => {
			// Arrange
			message.value.lastEditActorId = 'deleted_users'
			message.value.lastEditActorType = 'deleted_users'
			// Act
			const result = useMessageInfo(message)
			// Assert
			expect(result.lastEditor.value).toBe('(edited by a deleted user)')
		})

		test('return edited by you when the editor is the current user', () => {
			// Arrange
			message.value.actorId = 'user-id-2'
			message.value.actorType = ATTENDEE.ACTOR_TYPE.USERS
			// Act
			const result = useMessageInfo(message)
			// Assert
			expect(result.lastEditor.value).toBe('(edited by you)')
		})
	})
})
