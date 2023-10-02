<!--
  - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="wrapper wrapper--system">
		<div v-for="messagesCollapsed in messagesGroupedBySystemMessage"
			:key="messagesCollapsed.id"
			class="messages-group__system">
			<ul v-if="messagesCollapsed.messages?.length > 1"
				class="messages messages--header">
				<Message v-bind="createCombinedSystemMessage(messagesCollapsed)"
					is-combined-system-message
					:is-combined-system-message-collapsed="messagesCollapsed.collapsed"
					:next-message-id="getNextMessageId(messagesCollapsed.messages.at(-1))"
					:previous-message-id="getPrevMessageId(messagesCollapsed.messages.at(0))"
					@toggle-combined-system-message="toggleCollapsed(messagesCollapsed)" />
			</ul>
			<ul v-show="messagesCollapsed.messages?.length === 1 || !messagesCollapsed.collapsed"
				class="messages"
				:class="{'messages--collapsed': messagesCollapsed.messages?.length > 1}">
				<Message v-for="message in messagesCollapsed.messages"
					:key="message.id"
					v-bind="message"
					:next-message-id="getNextMessageId(message)"
					:previous-message-id="getPrevMessageId(message)" />
			</ul>
		</div>
	</div>
</template>

<script>
import Message from './Message/Message.vue'

import { useCombinedSystemMessage } from '../../../composables/useCombinedSystemMessage.js'

// List only sortable messages with order, in which they should be sorted
const MESSAGES = {
	user_added: 1,
	user_removed: 1,
	moderator_promoted: 11,
	guest_moderator_promoted: 11,
	moderator_demoted: 11,
	guest_moderator_demoted: 11,
	call_started: 20,
	recording_started: 21,
	call_joined: 22,
	call_left: 22,
	call_ended: 23,
	call_ended_everyone: 23,
}

export default {
	name: 'MessagesSystemGroup',

	components: {
		Message,
	},

	inheritAttrs: false,

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

	expose: ['highlightMessage'],

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

	watch: {
		messages: {
			deep: true,
			immediate: true,
			handler(value) {
				this.messagesGroupedBySystemMessage = this.groupMessages(this.sortMessages(value))
			},
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

			// Group users added by one actor
			if (message1.systemMessage === 'user_added'
				&& message1.systemMessage === message2.systemMessage
				&& message1.actorId === message2.actorId
				&& message1.actorType === message2.actorType) {
				return 'user_added'
			}

			// Group users reconnected in a minute
			if (message1.systemMessage === 'call_joined'
				&& message2.systemMessage === 'call_left'
				&& message1.timestamp - message2.timestamp < 60 * 1000
				&& message1.actorId === message2.actorId
				&& message1.actorType === message2.actorType) {
				return 'call_reconnected'
			}

			// Group users joined call one by one
			if (message1.systemMessage === 'call_joined'
				&& message1.systemMessage === message2.systemMessage) {
				return 'call_joined'
			}

			// Group users left call one by one
			if (message1.systemMessage === 'call_left'
				&& message1.systemMessage === message2.systemMessage) {
				return 'call_left'
			}

			// Group users promoted one by one
			if ((message1.systemMessage === 'moderator_promoted' || message1.systemMessage === 'guest_moderator_promoted')
				&& (message2.systemMessage === 'moderator_promoted' || message2.systemMessage === 'guest_moderator_promoted')) {
				return 'moderator_promoted'
			}

			// Group users demoted one by one
			if ((message1.systemMessage === 'moderator_demoted' || message1.systemMessage === 'guest_moderator_demoted')
				&& (message2.systemMessage === 'moderator_demoted' || message2.systemMessage === 'guest_moderator_demoted')) {
				return 'moderator_demoted'
			}

			return ''
		},

		sortMessages(messages) {
			return messages.slice().sort((message1, message2) => {
				// Don't sort messages if they're not intended to be sorted
				if (!MESSAGES[message1.systemMessage] || !MESSAGES[message2.systemMessage]) {
					return 0
				}

				// Don't sort related system messages (call join - call left) between each other
				return MESSAGES[message1.systemMessage] - MESSAGES[message2.systemMessage]
			})
		},

		groupMessages(messages) {
			const groups = []
			let lastMessage = null
			let forceNextGroup = false
			for (const message of messages) {
				const groupingType = this.messagesShouldBeGrouped(message, lastMessage)
				if (!groupingType || forceNextGroup) {
					groups.push({ id: message.id, messages: [message], type: '', collapsed: this.groupIsCollapsed[message.id] ?? true })
					forceNextGroup = false
				} else {
					if (groupingType === 'call_reconnected') {
						groups.push({ id: message.id, messages: [groups.at(-1).messages.pop()], type: '', collapsed: this.groupIsCollapsed[message.id] ?? true })
						forceNextGroup = true
					}
					groups.at(-1).messages.push(message)
					groups.at(-1).type = groupingType
				}
				lastMessage = message
			}

			groups.forEach(group => {
				if (this.groupIsCollapsed[group.id] === undefined) {
					this.groupIsCollapsed[group.id] = true
				}
			})
			return groups
		},

		toggleCollapsed(messages) {
			this.$set(messages, 'collapsed', !messages.collapsed)
			this.groupIsCollapsed[messages.id] = !this.groupIsCollapsed[messages.id]
		},

		getNextMessageId(message) {
			const nextMessage = this.messages[this.messages.findIndex(searchedMessage => searchedMessage.id === message.id) + 1]
			return nextMessage?.id || this.nextMessageId
		},

		getPrevMessageId(message) {
			const prevMessage = this.messages[this.messages.findIndex(searchedMessage => searchedMessage.id === message.id) - 1]
			return prevMessage?.id || this.previousMessageId
		},

		highlightMessage(messageId) {
			for (const message of this.$refs.message) {
				if (message.id === messageId) {
					message.highlightMessage()
					break
				}
			}
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
	max-width: $messages-list-max-width;
	display: flex;
	margin: auto;
	padding: 0;
	&--system {
		flex-direction: column;
		padding-left: calc(var(--default-clickable-area) + 8px);
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

	&--header {
	}
	&--collapsed {
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-hover);
	}
}
</style>
