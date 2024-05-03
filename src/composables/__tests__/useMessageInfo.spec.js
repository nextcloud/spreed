/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { computed } from 'vue'

import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../constants.js'
import { useConversationInfo } from '../useConversationInfo.js'
import { useMessageInfo } from '../useMessageInfo.js'
import { useStore } from '../useStore.js'

jest.mock('@nextcloud/capabilities', () => ({
	getCapabilities: jest.fn(() => ({
		spreed: {
			features: ['edit-messages'],
		},
	}))
}))
jest.mock('../useStore.js')
jest.mock('../useConversationInfo.js')

describe('message actions', () => {
	let messageProps
	let conversationProps
	let mockConversationInfo
	const TOKEN = 'XXTOKENXX'

	jest.useFakeTimers().setSystemTime(new Date('2024-05-01 17:00:00'))

	beforeEach(() => {
		messageProps = {
			message: 'test message',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorId: 'user-id-1',
			actorDisplayName: 'user-display-name-1',
			messageParameters: {},
			id: 123,
			isTemporary: false,
			isFirstMessage: true,
			isReplyable: true,
			isTranslationAvailable: false,
			canReact: true,
			isReactionsMenuOpen: false,
			isActionMenuOpen: false,
			isEmojiPickerOpen: false,
			isLastRead: false,
			isForwarderOpen: false,
			timestamp: new Date('2024-05-01 16:15:00').getTime() / 1000,
			token: TOKEN,
			systemMessage: '',
			messageType: 'comment',
			previousMessageId: 100,
			participant: {
				actorId: 'user-id-1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				participantType: PARTICIPANT.TYPE.USER,
			},
			showCommonReadIcon: true,
			showSentIcon: true,
			commonReadIconTooltip: '',
			sentIconTooltip: '',
		}
		conversationProps = {
			token: TOKEN,
			lastCommonReadMessage: 0,
			type: CONVERSATION.TYPE.GROUP,
			readOnly: CONVERSATION.STATE.READ_WRITE,
		}
		useStore.mockReturnValue({
			getters: {
				message: () => messageProps,
				conversation: () => conversationProps,
				getActorId: () => 'user-id-1',
				getActorType: () => ATTENDEE.ACTOR_TYPE.USERS,
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
		messageProps.timestamp = new Date('2024-05-01 7:20:00').getTime() / 1000
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isDeleteable.value).toBe(false)
	})

	test('message is not deleteable when the conversation is read-only', () => {
		// Arrange
		mockConversationInfo = {
			isOneToOneConversation: computed(() => false),
			isConversationReadOnly: computed(() => true),
			isConversationModifiable: computed(() => true),
		}
		useConversationInfo.mockReturnValue(mockConversationInfo)
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isDeleteable.value).toBe(false)
	})

	test('file message is deleteable', () => {
		// Arrange
		messageProps.message = '{file}'
		messageProps.messageParameters.file = {}
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isFileShare.value).toBe(true)
		expect(result.isFileShareWithoutCaption.value).toBe(true)
		expect(result.isDeleteable.value).toBe(true)
	})

	test('other people messages are not deleteable for non-moderators', () => {
		// Arrange
		messageProps.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isDeleteable.value).toBe(false)
	})

	test('other people messages are deleteable for moderators', () => {
		// Arrange
		messageProps.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		useStore.mockReturnValue({
			getters: {
				message: () => messageProps,
				conversation: () => conversationProps,
				getActorId: () => 'user-id-1',
				getActorType: () => ATTENDEE.ACTOR_TYPE.MODERATOR,
				isModerator: true,
			},
		})
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isDeleteable.value).toBe(true)
	})

	test('other people message is not deleteable in one to one conversations', () => {
		// Arrange
		messageProps.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.ONE_TO_ONE
		useStore.mockReturnValue({
			getters: {
				message: () => messageProps,
				conversation: () => conversationProps,
				getActorId: () => 'user-id-1',
				getActorType: () => ATTENDEE.ACTOR_TYPE.USER,
				isModerator: false,
			},
		})
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
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
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(true)
		expect(result.isEditable.value).toBe(true)
	})

	test('moderator can edit other people messages', () => {
		// Arrange
		messageProps.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		useStore.mockReturnValue({
			getters: {
				message: () => messageProps,
				conversation: () => conversationProps,
				getActorId: () => 'user-id-1',
				getActorType: () => ATTENDEE.ACTOR_TYPE.MODERATOR,
				isModerator: true,
			},
		})
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isEditable.value).toBe(true)
	})

	test('user can not edit other people messages', () => {
		// Arrange
		messageProps.actorId = 'another-user'
		conversationProps.type = CONVERSATION.TYPE.GROUP
		useStore.mockReturnValue({
			getters: {
				message: () => messageProps,
				conversation: () => conversationProps,
				getActorId: () => 'user-id-1',
				getActorType: () => ATTENDEE.ACTOR_TYPE.USERS,
				isModerator: false,
			},
		})
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isEditable.value).toBe(false)
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
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isEditable.value).toBe(false)
	})

	test('error handling, return false when conversation or message is not available', () => {
		// Arrange
		useStore.mockReturnValue({
			getters: {
				conversation: () => null,
				message: () => null,
			},
		})
		// Act
		const result = useMessageInfo(messageProps.token, messageProps.id)
		// Assert
		expect(result.isEditable.value).toBe(false)
		expect(result.isDeleteable.value).toBe(false)
		expect(result.isCurrentUserOwnMessage.value).toBe(false)
		expect(result.isObjectShare.value).toBe(false)
		expect(result.isConversationModifiable.value).toBe(false)
		expect(result.isConversationReadOnly.value).toBe(false)
		expect(result.isFileShareWithoutCaption.value).toBe(false)
		expect(result.isFileShare.value).toBe(false)
	})

})
