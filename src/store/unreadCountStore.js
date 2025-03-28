/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Vue from 'vue'
import { emit } from '@nextcloud/event-bus'

export const EVENTS = {
	UNREAD_COUNT_UPDATED: 'talk:unread:updated'
}

/**
 * @typedef {object} UnreadCountEvent
 * @property {number} unreadMessages - Total number of unread messages
 * @property {number} unreadMentions - Total number of unread mentions
 * @property {number} unreadMentionDirect - Total number of unread direct mentions
 */

const state = {
	// Total counters
	totalUnreadMessages: 0,
	totalUnreadMentions: 0,
	totalUnreadMentionDirect: 0,

	// Maps to track per-conversation counts
	unreadMessagesMap: {},
	unreadMentionsMap: {},
	unreadMentionDirectMap: {},
}

const getters = {
	getTotalUnreadMessages: (state) => state.totalUnreadMessages,
	getTotalUnreadMentions: (state) => state.totalUnreadMentions,
	getTotalUnreadMentionDirect: (state) => state.totalUnreadMentionDirect,
}

const mutations = {
	/**
	 * Updates the counts for a single conversation and updates totals
	 *
	 * @param {object} state The store state
	 * @param {object} payload The mutation payload
	 * @param {string} payload.token The conversation token
	 * @param {number} payload.unreadMessages Number of unread messages
	 * @param {number} payload.unreadMentions Number of unread mentions
	 * @param {number} payload.unreadMentionDirect Number of unread direct mentions
	 */
	UPDATE_CONVERSATION_COUNTS(state, { token, unreadMessages, unreadMentions, unreadMentionDirect }) {
		// Calculate differences
		const messageDiff = (unreadMessages || 0) - (state.unreadMessagesMap[token] || 0)
		const mentionsDiff = (unreadMentions || 0) - (state.unreadMentionsMap[token] || 0)
		const mentionDirectDiff = (unreadMentionDirect || 0) - (state.unreadMentionDirectMap[token] || 0)

		// Update maps
		Vue.set(state.unreadMessagesMap, token, unreadMessages || 0)
		Vue.set(state.unreadMentionsMap, token, unreadMentions || 0)
		Vue.set(state.unreadMentionDirectMap, token, unreadMentionDirect || 0)

		// Update totals
		state.totalUnreadMessages += messageDiff
		state.totalUnreadMentions += mentionsDiff
		state.totalUnreadMentionDirect += mentionDirectDiff

		// Emit event with current totals
		emit(EVENTS.UNREAD_COUNT_UPDATED, {
			unreadMessages: state.totalUnreadMessages,
			unreadMentions: state.totalUnreadMentions,
			unreadMentionDirect: state.totalUnreadMentionDirect,
		})
	},

	/**
	 * Removes a conversation from tracking
	 *
	 * @param {object} state The store state
	 * @param {object} payload The mutation payload
	 * @param {string} payload.token The conversation token
	 */
	REMOVE_CONVERSATION(state, { token }) {
		// Subtract the conversation's counts from totals
		state.totalUnreadMessages -= state.unreadMessagesMap[token] || 0
		state.totalUnreadMentions -= state.unreadMentionsMap[token] || 0
		state.totalUnreadMentionDirect -= state.unreadMentionDirectMap[token] || 0

		// Remove from maps
		Vue.delete(state.unreadMessagesMap, token)
		Vue.delete(state.unreadMentionsMap, token)
		Vue.delete(state.unreadMentionDirectMap, token)

		// Emit event with current totals
		emit(EVENTS.UNREAD_COUNT_UPDATED, {
			unreadMessages: state.totalUnreadMessages,
			unreadMentions: state.totalUnreadMentions,
			unreadMentionDirect: state.totalUnreadMentionDirect,
		})
	},

	/**
	 * Resets all counters
	 *
	 * @param {object} state The store state
	 */
	RESET_ALL_COUNTERS(state) {
		state.totalUnreadMessages = 0
		state.totalUnreadMentions = 0
		state.totalUnreadMentionDirect = 0
		state.unreadMessagesMap = {}
		state.unreadMentionsMap = {}
		state.unreadMentionDirectMap = {}
	},
}

const actions = {
	/**
	 * Updates the counts for a conversation
	 *
	 * @param {object} context The store context
	 * @param {Function} context.commit Commit mutation
	 * @param {object} payload The action payload
	 * @param {string} payload.token The conversation token
	 * @param {number} payload.unreadMessages Number of unread messages
	 * @param {number} payload.unreadMentions Number of unread mentions
	 * @param {number} payload.unreadMentionDirect Number of unread direct mentions
	 */
	updateConversationCounts({ commit }, payload) {
		commit('UPDATE_CONVERSATION_COUNTS', payload)
	},

	/**
	 * Removes a conversation from tracking
	 *
	 * @param {object} context The store context
	 * @param {Function} context.commit Commit mutation
	 * @param {object} payload The action payload
	 * @param {string} payload.token The conversation token
	 */
	removeConversation({ commit }, payload) {
		commit('REMOVE_CONVERSATION', payload)
	},

	/**
	 * Recalculates the total unread counters from all conversations
	 * This is a compatibility action that uses the new tracking system
	 *
	 * @param {object} context The store context
	 * @param {Function} context.commit Commit mutation
	 * @param {Function} context.dispatch Dispatch action
	 * @param {object} context.rootGetters Root getters
	 */
	async recalculateTotalUnreadCounters({ commit, dispatch, rootGetters }) {
		const conversations = rootGetters.conversationsList

		// Reset all counters
		commit('RESET_ALL_COUNTERS')

		// If there are no conversations, we're done
		if (!conversations || conversations.length === 0) {
			return
		}

		// Update counters for each conversation
		conversations.forEach(conversation => {
			dispatch('updateConversationCounts', {
				token: conversation.token,
				unreadMessages: conversation.unreadMessages || 0,
				unreadMentions: conversation.unreadMention || 0,
				unreadMentionDirect: conversation.unreadMentionDirect || 0,
			})
		})

		// Log for debugging
		console.debug('Recalculated unread counters from', conversations.length, 'conversations')
	},
}

export default {
	namespaced: true,
	state,
	mutations,
	getters,
	actions,
} 