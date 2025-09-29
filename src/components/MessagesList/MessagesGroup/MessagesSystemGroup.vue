<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="wrapper wrapper--system">
		<div
			v-for="messagesCollapsed in messagesGroupedBySystemMessage"
			:key="messagesCollapsed.id"
			class="messages-group__system">
			<ul v-if="messagesCollapsed.messages?.length > 1" class="messages">
				<MessageItem
					is-combined-system-message
					:is-combined-system-message-collapsed="messagesCollapsed.collapsed"
					:next-message-id="getNextMessageId(messagesCollapsed.messages.at(-1))"
					:previous-message-id="getPrevMessageId(messagesCollapsed.messages.at(0))"
					:last-collapsed-message-id="messagesCollapsed.lastId"
					:message="createCombinedSystemMessage(messagesCollapsed)"
					@toggle-combined-system-message="toggleCollapsed(messagesCollapsed)" />
			</ul>
			<ul
				v-show="messagesCollapsed.messages?.length === 1 || !messagesCollapsed.collapsed"
				class="messages"
				:class="{ 'messages--collapsed': messagesCollapsed.messages?.length > 1 }">
				<MessageItem
					v-for="message in messagesCollapsed.messages"
					:key="message.id"
					:message="message"
					:is-collapsed-system-message="messagesCollapsed.messages?.length > 1"
					:last-collapsed-message-id="messagesCollapsed.lastId"
					:next-message-id="getNextMessageId(message)"
					:previous-message-id="getPrevMessageId(message)" />
			</ul>
		</div>
	</li>
</template>

<script>
import MessageItem from './Message/MessageItem.vue'
import { useCombinedSystemMessage } from '../../../composables/useCombinedSystemMessage.js'
import { MESSAGE } from '../../../constants.ts'

export default {
	name: 'MessagesSystemGroup',

	components: {
		MessageItem,
	},

	props: {
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * The messages object.
		 */
		messages: {
			type: Array,
			required: true,
		},

		previousMessageId: {
			type: [String, Number],
			default: 0,
		},

		nextMessageId: {
			type: [String, Number],
			default: 0,
		},
	},

	setup() {
		const { createCombinedSystemMessage } = useCombinedSystemMessage()

		return {
			createCombinedSystemMessage,
		}
	},

	data() {
		return {
			groupIsCollapsed: {},
			messagesGroupedBySystemMessage: [],
		}
	},

	computed: {
		lastReadMessageId() {
			return this.$store.getters.conversation(this.token)?.lastReadMessage
		},
	},

	watch: {
		messages: {
			deep: true,
			immediate: true,
			handler(value) {
				this.messagesGroupedBySystemMessage = this.groupMessages(value)
				this.updateCollapsedState()
			},
		},

		lastReadMessageId() {
			this.updateCollapsedState()
		},
	},

	methods: {
		/**
		 * Compare two messages to decide if they should be grouped
		 *
		 * @param {object} message1 The new message
		 * @param {string} message1.id The ID of the new message
		 * @param {string} message1.actorType Actor type of the new message
		 * @param {string} message1.actorId Actor id of the new message
		 * @param {string} message1.systemMessage System message of the new message
		 * @param {number} message1.timestamp Timestamp of the new message
		 * @param {null|object} message2 The previous message
		 * @param {string} message2.id The ID of the second message
		 * @param {string} message2.actorType Actor type of the previous message
		 * @param {string} message2.actorId Actor id of the previous message
		 * @param {string} message2.systemMessage System message of the second message
		 * @param {number} message2.timestamp Timestamp of the second message
		 * @return {string} Type of grouping if the messages should be grouped
		 */
		messagesShouldBeGrouped(message1, message2) {
			if (!message2) {
				return '' // No previous message
			}

			// Group users joined call
			if (message1.systemMessage === MESSAGE.SYSTEM_TYPE.CALL_JOINED
				&& message1.systemMessage === message2.systemMessage) {
				return MESSAGE.SYSTEM_TYPE.CALL_JOINED
			}

			// Group users left call
			if (message1.systemMessage === MESSAGE.SYSTEM_TYPE.CALL_LEFT
				&& message1.systemMessage === message2.systemMessage) {
				return MESSAGE.SYSTEM_TYPE.CALL_LEFT
			}

			if (message1.actorId !== message2.actorId
				|| message1.actorType !== message2.actorType) {
				return '' // Different actors
			}

			// Group users reconnected in a minute
			if (message1.systemMessage === MESSAGE.SYSTEM_TYPE.CALL_JOINED
				&& message2.systemMessage === MESSAGE.SYSTEM_TYPE.CALL_LEFT
				&& message1.timestamp - message2.timestamp < 60 * 1000) {
				return 'call_reconnected'
			}

			// Group users added by one actor
			if (message1.systemMessage === MESSAGE.SYSTEM_TYPE.USER_ADDED
				&& message1.systemMessage === message2.systemMessage) {
				return MESSAGE.SYSTEM_TYPE.USER_ADDED
			}

			// Group users removed by one actor
			if (message1.systemMessage === MESSAGE.SYSTEM_TYPE.USER_REMOVED
				&& message1.systemMessage === message2.systemMessage) {
				return MESSAGE.SYSTEM_TYPE.USER_REMOVED
			}

			// Group users promoted by one actor
			if ((message1.systemMessage === MESSAGE.SYSTEM_TYPE.MODERATOR_PROMOTED || message1.systemMessage === MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_PROMOTED)
				&& (message2.systemMessage === MESSAGE.SYSTEM_TYPE.MODERATOR_PROMOTED || message2.systemMessage === MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_PROMOTED)) {
				return MESSAGE.SYSTEM_TYPE.MODERATOR_PROMOTED
			}

			// Group users demoted by one actor
			if ((message1.systemMessage === MESSAGE.SYSTEM_TYPE.MODERATOR_DEMOTED || message1.systemMessage === MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_DEMOTED)
				&& (message2.systemMessage === MESSAGE.SYSTEM_TYPE.MODERATOR_DEMOTED || message2.systemMessage === MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_DEMOTED)) {
				return MESSAGE.SYSTEM_TYPE.MODERATOR_DEMOTED
			}

			return ''
		},

		updateCollapsedState() {
			for (const group of this.messagesGroupedBySystemMessage) {
				const isLastReadInsideGroup = this.lastReadMessageId >= group.id && this.lastReadMessageId < group.lastId
				if (isLastReadInsideGroup) {
					// If the last read message is inside the group, we should show the group expanded
					group.collapsed = false
				} else if (this.groupIsCollapsed[group.id] !== undefined) {
					// If the group was collapsed before, we should keep it collapsed
					group.collapsed = this.groupIsCollapsed[group.id]
				} else {
					// If the group is not collapsed, we should collapse it if it contains more than one message
					group.collapsed = group.messages.length > 1
				}
			}
		},

		groupMessages(messages) {
			const groups = []
			let lastMessage = null
			let forceNextGroup = false
			for (const message of messages) {
				const groupingType = this.messagesShouldBeGrouped(message, lastMessage)
				if (!groupingType || forceNextGroup) {
					// Adding a new group
					groups.push({ id: message.id, lastId: message.id, messages: [message], type: '', collapsed: undefined })
					forceNextGroup = false
				} else {
					// Adding a message to the existing group

					if (groupingType === 'call_reconnected') {
						groups.push({ id: message.id, lastId: message.id, messages: [groups.at(-1).messages.pop()], type: '', collapsed: undefined })
						groups.at(-1).lastId = groups.at(-1).messages.at(-1).id
						forceNextGroup = true
					}
					groups.at(-1).messages.push(message)
					groups.at(-1).lastId = message.id
					groups.at(-1).type = groupingType
				}
				lastMessage = message
			}
			return groups
		},

		toggleCollapsed(group) {
			// group is a local state from messagesGroupedBySystemMessage
			group.collapsed = !group.collapsed
			this.groupIsCollapsed[group.id] = group.collapsed
		},

		getNextMessageId(message) {
			const nextMessage = this.messages[this.messages.findIndex((searchedMessage) => searchedMessage.id === message.id) + 1]
			return nextMessage?.id || this.nextMessageId
		},

		getPrevMessageId(message) {
			const prevMessage = this.messages[this.messages.findIndex((searchedMessage) => searchedMessage.id === message.id) - 1]
			return prevMessage?.id || this.previousMessageId
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables';

.messages-group__system {
	display: flex;
	flex-direction: column;
}

.wrapper {
	display: flex;
	width: 100%;
	padding: 0;
	&--system {
		flex-direction: column;
		padding-inline-start: calc($messages-avatar-width);
	}
	&:focus {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.messages {
	flex: auto;
	display: flex;
	flex-direction: column;
	width: 100%;
	min-width: 0;

	&--collapsed {
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-hover);
	}
}
</style>
