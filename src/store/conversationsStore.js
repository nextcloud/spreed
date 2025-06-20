import { getCurrentUser } from '@nextcloud/auth'
import { showError, showInfo, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Vue from 'vue'
import {
	ATTENDEE,
	CALL,
	CONVERSATION,
	MESSAGE,
	PARTICIPANT,
	WEBINAR,
} from '../constants.ts'
import {
	deleteConversationAvatar,
	setConversationAvatar,
	setConversationEmojiAvatar,
} from '../services/avatarService.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import { getTalkConfig, hasTalkFeature } from '../services/CapabilitiesManager.ts'
import {
	addToFavorites,
	archiveConversation,
	changeListable,
	changeLobbyState,
	changeReadOnlyState,
	createConversation,
	createLegacyConversation,
	deleteConversation,
	fetchConversation,
	fetchConversations,
	makeConversationPrivate,
	makeConversationPublic,
	markAsImportant,
	markAsInsensitive,
	markAsSensitive,
	markAsUnimportant,
	removeFromFavorites,
	setCallPermissions,
	setConversationDescription,
	setConversationName,
	setConversationPassword,
	setConversationPermissions,
	setMentionPermissions,
	setMessageExpiration,
	setNotificationCalls,
	setNotificationLevel,
	setRecordingConsent,
	setSIPEnabled,
	unarchiveConversation,
	unbindConversationFromObject,
} from '../services/conversationsService.ts'
import {
	clearConversationHistory,
	setConversationUnread,
} from '../services/messagesService.ts'
import { addParticipant } from '../services/participantsService.js'
import {
	startCallRecording,
	stopCallRecording,
} from '../services/recordingService.js'
import { talkBroadcastChannel } from '../services/talkBroadcastChannel.js'
import { useBreakoutRoomsStore } from '../stores/breakoutRooms.ts'
import { useChatExtrasStore } from '../stores/chatExtras.js'
import { useFederationStore } from '../stores/federation.ts'
import { useGroupwareStore } from '../stores/groupware.ts'
import { useReactionsStore } from '../stores/reactions.js'
import { useSharedItemsStore } from '../stores/sharedItems.js'
import { useTalkHashStore } from '../stores/talkHash.js'
import { convertToUnix } from '../utils/formattedTime.ts'
import { getDisplayNamesList } from '../utils/getDisplayName.ts'

const forcePasswordProtection = getTalkConfig('local', 'conversations', 'force-passwords')
const supportConversationCreationPassword = hasTalkFeature('local', 'conversation-creation-password')
const supportConversationCreationAll = hasTalkFeature('local', 'conversation-creation-all')

const DUMMY_CONVERSATION = {
	token: '',
	displayName: t('spreed', 'Loading …'),
	isFavorite: false,
	isArchived: false,
	hasPassword: false,
	breakoutRoomMode: CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED,
	breakoutRoomStatus: CONVERSATION.BREAKOUT_ROOM_STATUS.STOPPED,
	canEnableSIP: false,
	type: CONVERSATION.TYPE.PUBLIC,
	participantFlags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
	participantType: PARTICIPANT.TYPE.USER,
	readOnly: CONVERSATION.STATE.READ_ONLY,
	listable: CONVERSATION.LISTABLE.NONE,
	mentions: CONVERSATION.MENTION_PERMISSIONS.EVERYONE,
	hasCall: false,
	canStartCall: false,
	lobbyState: WEBINAR.LOBBY.NONE,
	lobbyTimer: 0,
	attendeePin: '',
	isDummyConversation: true,
}

/**
 * Emit global event for user status update with the status from a 1-1 conversation
 *
 * @param {object} conversation - a 1-1 conversation
 */
function emitUserStatusUpdated(conversation) {
	emit('user_status:status.updated', {
		status: conversation.status,
		message: conversation.statusMessage,
		icon: conversation.statusIcon,
		clearAt: conversation.statusClearAt,
		userId: conversation.name,
	})
}

const state = {
	conversations: {
	},
	conversationsInitialised: false,
}

const getters = {
	conversations: (state) => state.conversations,
	/**
	 * List of all conversations sorted by isFavorite and lastActivity without breakout rooms
	 *
	 * @param {object} state state
	 * @return {object[]} sorted conversations list
	 */
	conversationsList: (state) => {
		return Object.values(state.conversations)
			// Filter out breakout rooms
			.filter((conversation) => conversation.objectType !== CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)
			// Sort by isFavorite and lastActivity
			.sort((conversation1, conversation2) => {
				if (conversation1.isFavorite !== conversation2.isFavorite) {
					return conversation1.isFavorite ? -1 : 1
				}
				return conversation2.lastActivity - conversation1.lastActivity
			})
	},
	/**
	 * List of all archived conversations sorted
	 *
	 * @param {object} state state
	 * @param {object} getters getters
	 * @return {object[]} sorted conversations list
	 */
	archivedConversationsList: (state, getters) => {
		return getters.conversationsList.filter((conversation) => conversation.isArchived)
	},
	/**
	 * Get a conversation providing its token
	 *
	 * @param {object} state state object
	 * @return {Function} The callback function returning the conversation object
	 */
	conversation: (state) => (token) => state.conversations[token],
	dummyConversation: (state) => Object.assign({}, DUMMY_CONVERSATION),
	isModerator: (state, getters, rootState, rootGetters) => {
		const conversation = getters.conversation(rootGetters.getToken())
		return conversation?.participantType === PARTICIPANT.TYPE.OWNER
			|| conversation?.participantType === PARTICIPANT.TYPE.MODERATOR
			|| conversation?.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR
	},
	isModeratorOrUser: (state, getters, rootState, rootGetters) => {
		const conversation = getters.conversation(rootGetters.getToken())
		return !conversation?.isDummyConversation
			&& (getters.isModerator
				|| conversation?.participantType === PARTICIPANT.TYPE.USER
				|| conversation?.participantType === PARTICIPANT.TYPE.USER_SELF_JOINED)
	},
	isInLobby: (state, getters, rootState, rootGetters) => {
		const conversation = getters.conversation(rootGetters.getToken())
		return conversation
			&& conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
			&& !getters.isModerator
			&& (conversation.permissions & PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) === 0
	},
	getConversationForUser: (state, getters) => {
		return (userId) => getters.conversationsList
			.find((conversation) => conversation.type === CONVERSATION.TYPE.ONE_TO_ONE && conversation.name === userId)
	},

	conversationsInitialised: (state) => state.conversationsInitialised,
}

const mutations = {
	/**
	 * Adds a conversation to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} conversation the conversation;
	 */
	addConversation(state, conversation) {
		Vue.set(state.conversations, conversation.token, conversation)
	},

	/**
	 * Update stored conversation
	 *
	 * @param {object} state the state
	 * @param {object} conversation the new conversation object
	 */
	updateConversation(state, conversation) {
		state.conversations[conversation.token] = conversation
	},

	/**
	 * Deletes a conversation from the store.
	 *
	 * @param {object} state current store state;
	 * @param {string} token the token of the conversation to delete;
	 */
	deleteConversation(state, token) {
		Vue.delete(state.conversations, token)
	},

	setConversationDescription(state, { token, description }) {
		Vue.set(state.conversations[token], 'description', description)
	},

	updateConversationLastReadMessage(state, { token, lastReadMessage }) {
		Vue.set(state.conversations[token], 'lastReadMessage', lastReadMessage)
	},

	updateConversationLastMessage(state, { token, lastMessage }) {
		Vue.set(state.conversations[token], 'lastMessage', lastMessage)
	},

	updateUnreadMessages(state, { token, unreadMessages, unreadMention, unreadMentionDirect }) {
		if (unreadMessages !== undefined) {
			Vue.set(state.conversations[token], 'unreadMessages', unreadMessages)
		}
		if (unreadMention !== undefined) {
			Vue.set(state.conversations[token], 'unreadMention', unreadMention)
		}
		if (unreadMentionDirect !== undefined) {
			Vue.set(state.conversations[token], 'unreadMentionDirect', unreadMentionDirect)
		}
	},

	setNotificationLevel(state, { token, notificationLevel }) {
		Vue.set(state.conversations[token], 'notificationLevel', notificationLevel)
	},

	setNotificationCalls(state, { token, notificationCalls }) {
		Vue.set(state.conversations[token], 'notificationCalls', notificationCalls)
	},

	setConversationPermissions(state, { token, permissions }) {
		Vue.set(state.conversations[token], 'defaultPermissions', permissions)
	},

	setCallPermissions(state, { token, permissions }) {
		Vue.set(state.conversations[token], 'callPermissions', permissions)
	},

	setMentionPermissions(state, { token, mentionPermissions }) {
		Vue.set(state.conversations[token], 'mentionPermissions', mentionPermissions)
	},

	setCallRecording(state, { token, callRecording }) {
		Vue.set(state.conversations[token], 'callRecording', callRecording)
	},

	setMessageExpiration(state, { token, seconds }) {
		Vue.set(state.conversations[token], 'messageExpiration', seconds)
	},

	setConversationHasPassword(state, { token, hasPassword }) {
		Vue.set(state.conversations[token], 'hasPassword', hasPassword)
	},

	setConversationsInitialised(state, value) {
		state.conversationsInitialised = value
	},
}

const actions = {
	/**
	 * Add a conversation to the store and index the displayName
	 *
	 * @param {object} context default store context;
	 * @param {object} conversation the conversation;
	 */
	addConversation(context, conversation) {
		if (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE) {
			emitUserStatusUpdated(conversation)
		}

		context.commit('addConversation', conversation)

		if (!conversation.attendeeId) {
			// Don't add a thumbnail participant if attendeeId is unknown
			return
		}

		// Add current user to a new conversation participants
		let currentUser = {
			uid: context.getters.getUserId(),
			displayName: context.getters.getDisplayName(),
		}

		// Fallback to getCurrentUser() only if it has not been set yet (as
		// getCurrentUser() needs to be overridden in public share pages as it
		// always returns an anonymous user).
		if (!currentUser.uid) {
			currentUser = getCurrentUser()
		}
		context.dispatch('addParticipantOnce', {
			token: conversation.token,
			participant: {
				inCall: conversation.participantFlags,
				lastPing: conversation.lastPing,
				sessionIds: [conversation.sessionId],
				participantType: conversation.participantType,
				permissions: conversation.permissions,
				attendeeId: conversation.attendeeId,
				actorType: conversation.actorType,
				actorId: conversation.actorId, // FIXME check public share page handling
				userId: currentUser ? currentUser.uid : '',
				displayName: currentUser && currentUser.displayName ? currentUser.displayName : '', // TODO guest name from localstore?
				status: '',
			},
		})
	},

	/**
	 * Update conversation in store according to a new conversation object
	 *
	 * @param {object} context store context
	 * @param {object} conversation the new conversation object
	 * @return {boolean} whether the conversation was changed
	 */
	updateConversationIfHasChanged(context, conversation) {
		const oldConversation = context.state.conversations[conversation.token]

		// Update conversation if the number of attributes differ
		// (e.g. lastMessage was added or removed, because we don't keep lastMessage as an empty object)
		if (Object.keys(oldConversation).length !== Object.keys(conversation).length) {
			context.commit('updateConversation', conversation)
			return true
		}

		// Update 1-1 conversation, if its status was changed
		if (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
			&& (oldConversation.status !== conversation.status
				|| oldConversation.statusMessage !== conversation.statusMessage
				|| oldConversation.statusIcon !== conversation.statusIcon
				|| oldConversation.statusClearAt !== conversation.statusClearAt
			)
		) {
			emitUserStatusUpdated(conversation)
			context.commit('updateConversation', conversation)
			return true
		}

		// Update conversation if lastActivity updated (e.g. new message came up, call state changed)
		if (oldConversation.lastActivity !== conversation.lastActivity) {
			context.commit('updateConversation', conversation)
			return true
		}

		// Check if any property were changed (no properties except status-related and lastMessage supposed to be added or deleted)
		for (const key of Object.keys(conversation)) {
			// "lastMessage" is the only property with non-primitive (object) value and cannot be compared by ===
			// If "lastMessage" was actually changed, it is already checked by "lastActivity"
			if (key !== 'lastMessage' && oldConversation[key] !== conversation[key]) {
				context.commit('updateConversation', conversation)
				return true
			}
		}

		return false
	},

	/**
	 * Delete a conversation from the store.
	 *
	 * @param {object} context default store context;
	 * @param {string} token the token of the conversation to be deleted;
	 */
	deleteConversation(context, token) {
		// FIXME: rename to deleteConversationsFromStore or a better name
		const chatExtrasStore = useChatExtrasStore()
		chatExtrasStore.purgeChatExtras(token)
		const groupwareStore = useGroupwareStore()
		groupwareStore.purgeGroupwareStore(token)
		const reactionsStore = useReactionsStore()
		reactionsStore.purgeReactionsStore(token)
		const sharedItemsStore = useSharedItemsStore()
		sharedItemsStore.purgeSharedItemsStore(token)
		context.dispatch('purgeMessagesStore', token)
		context.commit('deleteConversation', token)
		context.dispatch('purgeParticipantsStore', token)
		context.dispatch('cacheConversations')
	},

	/**
	 * Patch conversations:
	 * - Add new conversations
	 * - Remove conversations that are not in the new list
	 * - Update existing conversations
	 *
	 * @param {object} context default store context
	 * @param {object} payload the payload
	 * @param {object[]} payload.conversations new conversations list
	 * @param {boolean} payload.withRemoving whether to remove conversations that are not in the new list
	 * @param {boolean} payload.withCaching whether to cache conversations to BrowserStorage with patch
	 */
	patchConversations(context, { conversations, withRemoving = false, withCaching = false }) {
		let storeHasChanged = false
		const breakoutRoomsStore = useBreakoutRoomsStore()

		const currentConversations = context.state.conversations
		const newConversations = Object.fromEntries(conversations.map((conversation) => [conversation.token, conversation]))

		// Remove conversations that are not in the new list
		if (withRemoving) {
			for (const token of Object.keys(currentConversations)) {
				if (newConversations[token] === undefined) {
					context.dispatch('deleteConversation', token)
					storeHasChanged = true
				}
			}
		}

		// Add new conversations and patch existing ones
		for (const [token, newConversation] of Object.entries(newConversations)) {
			if (currentConversations[token] === undefined) {
				context.dispatch('addConversation', newConversation)
				storeHasChanged = true
			} else {
				const conversationHasChanged = context.dispatch('updateConversationIfHasChanged', newConversation)
				storeHasChanged = conversationHasChanged || storeHasChanged
			}

			if (newConversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM) {
				breakoutRoomsStore.addBreakoutRoom(newConversation.objectId, newConversation)
			}
		}

		if (withCaching && storeHasChanged) {
			context.dispatch('cacheConversations')
		}
	},

	/**
	 * Restores conversations from BrowserStorage and add them to the store state
	 *
	 * @param {object} context default store context
	 */
	restoreConversations(context) {
		const cachedConversations = BrowserStorage.getItem('cachedConversations')
		if (!cachedConversations?.length) {
			return
		}

		context.dispatch('patchConversations', {
			conversations: JSON.parse(cachedConversations),
			withRemoving: true,
		})

		context.commit('setConversationsInitialised', true)

		console.debug('Conversations have been restored from BrowserStorage')
	},

	/**
	 * Save conversations to BrowserStorage from the store state
	 *
	 * @param {object} context default store context
	 */
	cacheConversations(context) {
		const conversations = context.getters.conversationsList
		if (!conversations.length) {
			return
		}

		const serializedConversations = JSON.stringify(conversations)
		BrowserStorage.setItem('cachedConversations', serializedConversations)
		console.debug(`Conversations were saved to BrowserStorage. Estimated object size: ${(serializedConversations.length / 1024).toFixed(2)} kB`)
	},

	/**
	 * Delete a conversation from the server.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the token of the conversation to be deleted;
	 */
	async deleteConversationFromServer(context, { token }) {
		try {
			await deleteConversation(token)
			// upon success, also delete from store
			await context.dispatch('deleteConversation', token)
			talkBroadcastChannel.postMessage({ message: 'force-fetch-all-conversations', options: { all: true } })
		} catch (error) {
			console.error('Error while deleting the conversation: ', error)
		}
	},

	/**
	 * Delete all the messages from a conversation.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the token of the conversation whose history is
	 * to be cleared;
	 */
	async clearConversationHistory(context, { token }) {
		try {
			const response = await clearConversationHistory(token)
			const chatExtrasStore = useChatExtrasStore()
			chatExtrasStore.removeParentIdToReply(token)
			const reactionsStore = useReactionsStore()
			reactionsStore.purgeReactionsStore(token)
			const sharedItemsStore = useSharedItemsStore()
			sharedItemsStore.purgeSharedItemsStore(token)
			context.dispatch('purgeMessagesStore', token)
			return response
		} catch (error) {
			console.error(t('spreed', 'Error while clearing conversation history'), error)
		}
	},

	async toggleGuests({ commit, getters }, { token, allowGuests, password }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			const conversation = Object.assign({}, getters.conversation(token))
			if (allowGuests) {
				await makeConversationPublic(token, password)
				conversation.type = CONVERSATION.TYPE.PUBLIC
				showSuccess(t('spreed', 'You allowed guests'))
			} else {
				await makeConversationPrivate(token)
				conversation.type = CONVERSATION.TYPE.GROUP
				showSuccess(t('spreed', 'You disallowed guests'))
			}
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while changing the conversation public status: ', error)
			showError(allowGuests
				? t('spreed', 'Error occurred while allowing guests')
				: t('spreed', 'Error occurred while disallowing guests'))
		}
	},

	async toggleFavorite({ commit, getters }, { token, isFavorite }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			if (isFavorite) {
				await removeFromFavorites(token)
			} else {
				await addToFavorites(token)
			}

			const conversation = Object.assign({}, getters.conversations[token], { isFavorite: !isFavorite })

			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while changing the conversation favorite status: ', error)
		}
	},

	async toggleArchive(context, { token, isArchived }) {
		if (!context.getters.conversations[token]) {
			return
		}

		try {
			const response = isArchived
				? await unarchiveConversation(token)
				: await archiveConversation(token)
			context.commit('addConversation', response.data.ocs.data)
		} catch (error) {
			console.error('Error while changing the conversation archived status: ', error)
		}
	},

	async toggleImportant(context, { token, isImportant }) {
		if (!context.getters.conversations[token]) {
			return
		}

		try {
			const response = isImportant
				? await markAsImportant(token)
				: await markAsUnimportant(token)
			context.commit('addConversation', response.data.ocs.data)
		} catch (error) {
			console.error('Error while changing the conversation important status: ', error)
		}
	},

	async toggleSensitive(context, { token, isSensitive }) {
		if (!context.getters.conversations[token]) {
			return
		}

		try {
			const response = isSensitive
				? await markAsSensitive(token)
				: await markAsInsensitive(token)
			context.commit('addConversation', response.data.ocs.data)
		} catch (error) {
			console.error('Error while changing the conversation sensitive status: ', error)
		}
	},

	async toggleLobby({ commit, getters }, { token, enableLobby }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			const conversation = Object.assign({}, getters.conversations[token])
			if (enableLobby) {
				await changeLobbyState(token, WEBINAR.LOBBY.NON_MODERATORS)
				conversation.lobbyState = WEBINAR.LOBBY.NON_MODERATORS
				showSuccess(t('spreed', 'You restricted the conversation to moderators'))
			} else {
				await changeLobbyState(token, WEBINAR.LOBBY.NONE)
				conversation.lobbyState = WEBINAR.LOBBY.NONE
				showSuccess(t('spreed', 'You opened the conversation to everyone'))
			}
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error occurred while updating webinar lobby: ', error)
			if (enableLobby) {
				showError(t('spreed', 'Error occurred when restricting the conversation to moderator'))
			} else {
				showError(t('spreed', 'Error occurred when opening the conversation to everyone'))
			}
		}
	},

	async setConversationName({ commit, getters }, { token, name }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			await setConversationName(token, name)
			const conversation = Object.assign({}, getters.conversations[token], { displayName: name })
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while setting a name for conversation: ', error)
		}
	},

	async setConversationDescription({ commit }, { token, description }) {
		try {
			await setConversationDescription(token, description)
			commit('setConversationDescription', { token, description })
		} catch (error) {
			console.error('Error while setting a description for conversation: ', error)
		}
	},

	async setConversationPassword({ commit }, { token, newPassword }) {
		try {
			await setConversationPassword(token, newPassword)
			commit('setConversationHasPassword', { token, hasPassword: !!newPassword })
			if (newPassword !== '') {
				showSuccess(t('spreed', 'Conversation password has been saved'))
			} else {
				showSuccess(t('spreed', 'Conversation password has been removed'))
			}
		} catch (error) {
			console.error('Error while setting a password for conversation: ', error)
			if (error?.response?.data?.ocs?.data?.message) {
				showError(error.response.data.ocs.data.message)
			} else {
				showError(t('spreed', 'Error occurred while saving conversation password'))
			}
		}
	},

	async setReadOnlyState({ commit, getters }, { token, readOnly }) {
		if (!getters.conversations[token]) {
			return
		}
		try {
			await changeReadOnlyState(token, readOnly)
			const conversation = Object.assign({}, getters.conversations[token], { readOnly })
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while updating read-only state: ', error)
		}
	},

	async setListable({ commit, getters }, { token, listable }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			await changeListable(token, listable)
			const conversation = Object.assign({}, getters.conversations[token], { listable })
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while updating listable state: ', error)
		}
	},

	async setLobbyTimer({ commit, getters }, { token, timestamp }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			const conversation = Object.assign({}, getters.conversations[token], { lobbyTimer: timestamp })
			// The backend requires the state and timestamp to be set together.
			await changeLobbyState(token, conversation.lobbyState, timestamp)
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while updating webinar lobby: ', error)
		}
	},

	async setSIPEnabled({ commit, getters }, { token, state }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			await setSIPEnabled(token, state)
			const conversation = Object.assign({}, getters.conversations[token], { sipEnabled: state })
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while changing the SIP state for conversation: ', error)
		}
	},

	async setRecordingConsent({ commit, getters }, { token, state }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			await setRecordingConsent(token, state)
			const conversation = Object.assign({}, getters.conversations[token], { recordingConsent: state })
			commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while changing the recording consent state for conversation: ', error)
		}
	},

	async setConversationProperties({ commit, getters }, { token, properties }) {
		if (!getters.conversations[token]) {
			return
		}

		const conversation = Object.assign({}, getters.conversations[token], properties)

		commit('addConversation', conversation)
	},

	async markConversationUnread({ commit, dispatch, getters }, { token }) {
		if (!getters.conversations[token]) {
			return
		}

		try {
			const response = await setConversationUnread(token)
			dispatch('addConversation', response.data.ocs.data)
		} catch (error) {
			console.error('Error while setting the conversation as unread: ', error)
		}
	},

	async updateLastCommonReadMessage({ commit, getters }, { token, lastCommonReadMessage }) {
		if (!getters.conversations[token]) {
			return
		}

		const conversation = Object.assign({}, getters.conversations[token], { lastCommonReadMessage })

		commit('addConversation', conversation)
	},

	async updateConversationLastActive({ commit, getters }, token) {
		if (!getters.conversations[token]) {
			return
		}

		const conversation = Object.assign({}, getters.conversations[token], {
			lastActivity: convertToUnix(Date.now()),
		})

		commit('addConversation', conversation)
	},

	async updateConversationLastMessage({ commit }, { token, lastMessage }) {
		/**
		 * Only use the last message as lastMessage when:
		 * 1. It's not a command reply
		 * 2. It's not a reaction or deletion of a reaction
		 * 3. It's not a deletion of a message
		 */
		if ((lastMessage.actorType !== ATTENDEE.ACTOR_TYPE.BOTS
			|| lastMessage.actorId === ATTENDEE.CHANGELOG_BOT_ID)
		&& lastMessage.systemMessage !== 'reaction'
		&& lastMessage.systemMessage !== 'poll_voted'
		&& lastMessage.systemMessage !== 'reaction_deleted'
		&& lastMessage.systemMessage !== 'reaction_revoked'
		&& lastMessage.systemMessage !== 'message_deleted'
		&& lastMessage.systemMessage !== 'message_edited') {
			commit('updateConversationLastMessage', { token, lastMessage })
		}
	},

	async updateConversationLastMessageFromNotification({ getters, commit }, { notification }) {
		const [token, messageId] = notification.objectId.split('/')

		if (!getters.conversations[token]) {
			// Conversation not loaded yet, skipping
			return
		}

		const conversation = Object.assign({}, getters.conversations[token])
		if (conversation.lastMessage?.id === parseInt(messageId, 10)
			|| conversation.lastMessage?.timestamp >= convertToUnix(new Date(notification.datetime))) {
			// Already updated from other source, skipping
			return
		}

		const actor = notification.subjectRichParameters.user || notification.subjectRichParameters.guest || {
			type: 'guest',
			id: 'unknown',
			name: t('spreed', 'Guest'),
		}

		const lastMessage = {
			token,
			id: parseInt(messageId, 10),
			actorType: actor.type + 's',
			actorId: actor.id,
			actorDisplayName: actor.name,
			message: notification.messageRich,
			messageParameters: notification.messageRichParameters,
			timestamp: convertToUnix(new Date(notification.datetime)),

			// Inaccurate but best effort from here on:
			expirationTimestamp: 0,
			isReplyable: true,
			messageType: MESSAGE.TYPE.COMMENT,
			reactions: {},
			referenceId: '',
			systemMessage: '',
		}

		const unreadCounterUpdate = {
			token,
			unreadMessages: conversation.unreadMessages,
			unreadMention: conversation.unreadMention,
			unreadMentionDirect: conversation.unreadMentionDirect,
		}

		if (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE) {
			unreadCounterUpdate.unreadMessages++
			unreadCounterUpdate.unreadMention++
			unreadCounterUpdate.unreadMentionDirect = true
		} else {
			unreadCounterUpdate.unreadMessages++
			Object.keys(notification.messageRichParameters).forEach(function(p) {
				const parameter = notification.messageRichParameters[p]
				if (parameter.type === 'user' && parameter.id === notification.user) {
					unreadCounterUpdate.unreadMention++
					unreadCounterUpdate.unreadMentionDirect = true
				} else if (parameter.type === 'call' && parameter.id === token) {
					unreadCounterUpdate.unreadMention++
				}
			})
		}
		conversation.lastActivity = lastMessage.timestamp

		commit('addConversation', conversation)
		commit('updateConversationLastMessage', { token, lastMessage })
		commit('updateUnreadMessages', unreadCounterUpdate)
	},

	async updateCallStateFromNotification({ getters, commit }, { notification }) {
		const token = notification.objectId

		if (!getters.conversations[token]) {
			// Conversation not loaded yet, skipping
			return
		}

		const activeSince = convertToUnix(new Date(notification.datetime))

		// Check if notification information is older than in known conversation object
		if (activeSince < getters.conversations[token].lastActivity) {
			return
		}

		const conversation = Object.assign({}, getters.conversations[token], {
			hasCall: true,
			callFlag: PARTICIPANT.CALL_FLAG.WITH_VIDEO,
			activeSince,
			lastActivity: activeSince,
			callStartTime: activeSince,
		})

		// Inaccurate but best effort from here on:
		const lastMessage = {
			token,
			id: 'temp' + activeSince,
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			actorId: 'unknown',
			actorDisplayName: t('spreed', 'Guest'),
			message: notification.subjectRich,
			messageParameters: notification.subjectRichParameters,
			timestamp: activeSince,
			messageType: MESSAGE.TYPE.SYSTEM,
			systemMessage: 'call_started',
			expirationTimestamp: 0,
			isReplyable: false,
			reactions: {},
			referenceId: '',
		}

		commit('updateConversationLastMessage', { token, lastMessage })
		commit('addConversation', conversation)
	},

	async updateConversationLastReadMessage({ commit }, { token, lastReadMessage }) {
		commit('updateConversationLastReadMessage', { token, lastReadMessage })
	},

	async overwriteHasCallByChat({ commit, dispatch }, { token, hasCall, lastActivity }) {
		dispatch('setConversationProperties', {
			token,
			properties: {
				hasCall,
				callFlag: hasCall ? PARTICIPANT.CALL_FLAG.IN_CALL : PARTICIPANT.CALL_FLAG.DISCONNECTED,
				lastActivity,
				callStartTime: hasCall ? lastActivity : 0,
			},
		})
	},

	async fetchConversation({ dispatch }, { token }) {
		const talkHashStore = useTalkHashStore()
		try {
			talkHashStore.clearMaintenanceMode()
			const response = await fetchConversation(token)
			talkHashStore.updateTalkVersionHash(response)
			dispatch('addConversation', response.data.ocs.data)
			return response
		} catch (error) {
			if (error?.response) {
				talkHashStore.checkMaintenanceMode(error.response)
			}
			throw error
		}
	},

	async fetchConversations({ dispatch, commit }, { modifiedSince, includeLastMessage = 1 }) {
		const talkHashStore = useTalkHashStore()
		const federationStore = useFederationStore()
		try {
			talkHashStore.clearMaintenanceMode()
			modifiedSince = modifiedSince || 0
			const response = await fetchConversations({
				modifiedSince,
				includeStatus: 1,
				includeLastMessage,
			})
			talkHashStore.updateTalkVersionHash(response)
			federationStore.updatePendingSharesCount(response.headers['x-nextcloud-talk-federation-invites'])

			dispatch('patchConversations', {
				conversations: response.data.ocs.data,
				// Remove only when fetching a full list, not fresh updates
				withRemoving: modifiedSince === 0,
				withCaching: true,
			})

			// Inform other tabs about successful fetch
			talkBroadcastChannel.postMessage({
				message: 'update-conversations',
				conversations: response.data.ocs.data,
				invites: response.headers['x-nextcloud-talk-federation-invites'],
				withRemoving: modifiedSince === 0,
			})
			commit('setConversationsInitialised', true)
			return response
		} catch (error) {
			if (error?.response) {
				talkHashStore.checkMaintenanceMode(error.response)
			}
			throw error
		}
	},

	async setNotificationLevel({ commit }, { token, notificationLevel }) {
		try {
			await setNotificationLevel(token, notificationLevel)
			commit('setNotificationLevel', { token, notificationLevel: +notificationLevel })
		} catch (error) {
			console.error('Error while setting the notification level: ', error)
		}
	},

	async setNotificationCalls({ commit }, { token, notificationCalls }) {
		try {
			await setNotificationCalls(token, notificationCalls)
			commit('setNotificationCalls', { token, notificationCalls })
		} catch (error) {
			console.error('Error while setting the call notification level: ', error)
		}
	},

	/**
	 * Creates a new one to one conversation in the backend
	 * with the given actor then adds it to the store.
	 *
	 * @param {object} context default store context;
	 * @param {string} actorId actor id;
	 */
	async createOneToOneConversation(context, actorId) {
		try {
			const response = supportConversationCreationAll
				? await createConversation({
						roomType: CONVERSATION.TYPE.ONE_TO_ONE,
						participants: { users: [actorId] },
					})
				: await createLegacyConversation({
						roomType: CONVERSATION.TYPE.ONE_TO_ONE,
						invite: actorId,
					})
			await context.dispatch('addConversation', response.data.ocs.data)
			return response.data.ocs.data
		} catch (error) {
			console.error('Error creating new one to one conversation: ', error)
		}
	},

	/**
	 * Creates a new group conversation the new conversation from one-to-one conversation
	 * with another one-to-one participant and given user
	 *
	 * @param {object} context default store context
	 * @param {object} payload action payload
	 * @param {string} payload.token one-to-one conversation token
	 * @param {Array} payload.newParticipants selected participants to be added (should include second participant form original conversation)
	 */
	async extendOneToOneConversation(context, { token, newParticipants }) {
		const conversation = context.getters.conversation(token)
		const participants = [
			{ id: conversation.actorId, source: conversation.actorType, label: context.rootGetters.getDisplayName() },
			...newParticipants,
		]
		const roomName = getDisplayNamesList(participants.map((participant) => participant.label), CONVERSATION.MAX_NAME_LENGTH)

		return context.dispatch('createGroupConversation', {
			roomName,
			roomType: CONVERSATION.TYPE.GROUP,
			objectType: CONVERSATION.OBJECT_TYPE.EXTENDED,
			objectId: token,
			participants,
		})
	},

	/**
	 * Creates a new private or public conversation, adds it to the store
	 *
	 * @param {object} context default store context;
	 * @param {object} payload action payload;
	 * @param {string} payload.roomName displayed name for a new conversation
	 * @param {number} payload.roomType whether a conversation is public or private
	 * @param {string} [payload.objectType] object type of new conversation, if applicable
	 * @param {string} [payload.objectId] reference to initial conversation, if applicable
	 * @param {string} [payload.password] password for a public conversation
	 * @param {string} [payload.description] description for a new conversation
	 * @param {number} [payload.listable] whether a conversation is opened to registered users
	 * @param {Array} [payload.participants] list of participants
	 * @param {object} [payload.avatar] avatar object: { emoji, color } | { file }
	 * @return {object} new conversation object
	 */
	async createGroupConversation(context, {
		roomName,
		roomType,
		objectType,
		objectId,
		password,
		description,
		listable,
		participants,
		avatar,
	}) {
		if (roomType === CONVERSATION.TYPE.PUBLIC && forcePasswordProtection && !password) {
			throw new Error('password_required')
		}

		try {
			let response
			if (supportConversationCreationAll) {
				const participantsMap = participants?.reduce((map, participant) => {
					// FIXME type Record<'users'|'federated_users'|'groups'|'emails'|'phones'|'teams', string[]>
					const source = participant.source === 'circles' ? 'teams' : participant.source
					if (!['users', 'federated_users', 'groups', 'emails', 'phones', 'teams'].includes(source)) {
						return map
					}

					if (!map[source]) {
						map[source] = []
					}
					map[source].push(participant.id)
					return map
				}, {})

				response = await createConversation({
					roomType,
					roomName,
					objectType,
					objectId,
					password,
					description,
					listable,
					emoji: avatar?.emoji,
					avatarColor: avatar?.color,
					participants: participantsMap,
				})
			} else {
				response = await createLegacyConversation({
					roomType,
					roomName,
					password: supportConversationCreationPassword ? password : undefined,
				})
			}

			const token = response.data.ocs.data.token
			context.dispatch('addConversation', response.data.ocs.data)

			const promises = []

			// FIXME Both advanced and legacy API do not support picture avatar upload on creation
			if (avatar?.file) {
				promises.push(context.dispatch('setConversationAvatarAction', { token, file: avatar.file }))
			}

			if (!supportConversationCreationAll) {
				if (avatar?.emoji) {
					promises.push(context.dispatch('setConversationEmojiAvatarAction', { token, emoji: avatar.emoji, color: avatar.color }))
				}

				if (description) {
					promises.push(context.dispatch('setConversationDescription', { token, description }))
				}

				if (password && !supportConversationCreationPassword) {
					promises.push(setConversationPassword(token, password))
				}

				if (listable !== CONVERSATION.LISTABLE.NONE) {
					promises.push(context.dispatch('setListable', { token, listable }))
				}

				for (const participant of participants) {
					promises.push(addParticipant(token, participant.id, participant.source))
				}
			}

			await Promise.all(promises)

			return context.getters.conversation(token)
		} catch (error) {
			return Promise.reject(error)
		}
	},

	async setConversationPermissions(context, { token, permissions }) {
		try {
			await setConversationPermissions(token, permissions)
			context.commit('setConversationPermissions', { token, permissions })
		} catch (error) {
			console.error('Error while updating conversation permissions: ', error)
		}
	},

	async setMessageExpiration({ commit }, { token, seconds }) {
		try {
			await setMessageExpiration(token, seconds)
			commit('setMessageExpiration', { token, seconds })
		} catch (error) {
			console.error('Error while setting conversation message expiration: ', error)
		}
	},

	async setCallPermissions(context, { token, permissions }) {
		try {
			await setCallPermissions(token, permissions)
			context.commit('setCallPermissions', { token, permissions })
		} catch (error) {
			console.error('Error while updating call permissions: ', error)
		}
	},

	async setMentionPermissions(context, { token, mentionPermissions }) {
		try {
			await setMentionPermissions(token, mentionPermissions)
			context.commit('setMentionPermissions', { token, mentionPermissions })
		} catch (error) {
			console.error('Error while updating mention permissions: ', error)
		}
	},

	async startCallRecording(context, { token, callRecording }) {
		try {
			await startCallRecording(token, callRecording)
		} catch (e) {
			console.error(e)
		}

		const startingCallRecording = callRecording === CALL.RECORDING.VIDEO ? CALL.RECORDING.VIDEO_STARTING : CALL.RECORDING.AUDIO_STARTING

		showSuccess(t('spreed', 'Call recording is starting.'))
		context.commit('setCallRecording', { token, callRecording: startingCallRecording })
	},

	async stopCallRecording(context, { token }) {
		const previousCallRecordingStatus = context.getters.conversation(token).callRecording

		try {
			await stopCallRecording(token)
		} catch (e) {
			console.error(e)
		}

		if (previousCallRecordingStatus === CALL.RECORDING.AUDIO_STARTING
			|| previousCallRecordingStatus === CALL.RECORDING.VIDEO_STARTING) {
			showInfo(t('spreed', 'Call recording stopped while starting.'))
		} else {
			showInfo(t('spreed', 'Call recording stopped. You will be notified once the recording is available.'), {
				timeout: TOAST_PERMANENT_TIMEOUT,
			})
		}
		context.commit('setCallRecording', { token, callRecording: CALL.RECORDING.OFF })
	},

	async setConversationAvatarAction(context, { token, file }) {
		try {
			const response = await setConversationAvatar(token, file)
			const conversation = response.data.ocs.data
			context.commit('addConversation', conversation)
			showSuccess(t('spreed', 'Conversation picture set'))
		} catch (error) {
			throw new Error(error.response?.data?.ocs?.data?.message ?? error.message)
		}
	},

	async setConversationEmojiAvatarAction(context, { token, emoji, color }) {
		try {
			const response = await setConversationEmojiAvatar(token, emoji, color)
			const conversation = response.data.ocs.data
			context.commit('addConversation', conversation)
			showSuccess(t('spreed', 'Conversation picture set'))
		} catch (error) {
			throw new Error(error.response?.data?.ocs?.data?.message ?? error.message)
		}
	},

	async deleteConversationAvatarAction(context, { token, file }) {
		try {
			const response = await deleteConversationAvatar(token, file)
			const conversation = response.data.ocs.data
			context.commit('addConversation', conversation)
			showSuccess(t('spreed', 'Conversation picture deleted'))
		} catch (error) {
			showError(t('spreed', 'Could not delete the conversation picture'))
		}
	},

	async unbindConversationFromObject(context, { token }) {
		try {
			const response = await unbindConversationFromObject(token)
			const conversation = response.data.ocs.data
			context.commit('addConversation', conversation)
		} catch (error) {
			console.error('Error while unbinding conversation from object: ', error)
			showError(t('spreed', 'Could not remove the automatic expiration'))
		}
	},
}

export default { state, mutations, getters, actions }
