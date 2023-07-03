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
	<div class="message-group">
		<div v-if="dateSeparator"
			class="message-group__date-header">
			<span class="date"
				role="heading"
				aria-level="3">{{ dateSeparator }}</span>
		</div>
		<div class="wrapper wrapper--system">
			<div v-for="messagesCollapsed in messagesGroupedBySystemMessage"
				:key="messagesCollapsed.id"
				class="message-group__system">
				<ul v-if="messagesCollapsed.messages?.length > 1"
					class="messages messages--header">
					<Message v-bind="createCombinedSystemMessage(messagesCollapsed)"
						:next-message-id="getNextMessageId(messagesCollapsed.messages.at(-1))"
						:previous-message-id="getPrevMessageId(messagesCollapsed.messages.at(0))" />
					<NcButton type="tertiary"
						class="messages--header__toggle"
						:aria-label="t('spreed', 'Show or collapse system messages')"
						@click="toggleCollapsed(messagesCollapsed)">
						<template #icon>
							<UnfoldMore v-if="messagesCollapsed.collapsed" />
							<UnfoldLess v-else />
						</template>
					</NcButton>
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
	</div>
</template>

<script>
import cloneDeep from 'lodash/cloneDeep.js'

import UnfoldLess from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import UnfoldMore from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import Message from './Message/Message.vue'

export default {
	name: 'MessagesSystemGroup',

	components: {
		Message,
		NcButton,
		UnfoldLess,
		UnfoldMore,
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
		/**
		 * The message date separator.
		 */
		dateSeparator: {
			type: String,
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

	data() {
		return {
			groupIsCollapsed: {},
			messagesGroupedBySystemMessage: [],
		}
	},

	watch: {
		messages: {
			immediate: true,
			handler(value) {
				this.messagesGroupedBySystemMessage = this.groupMessages(value)
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

		groupMessages(messages) {
			const groups = []
			let lastMessage = null
			let forceNextGroup = false
			for (const message of messages) {
				const groupingType = this.messagesShouldBeGrouped(message, lastMessage)
				if (!groupingType || forceNextGroup) {
					groups.push({ messages: [message], type: '', collapsed: true })
					forceNextGroup = false
				} else {
					if (groupingType === 'call_reconnected') {
						groups.push({ messages: [groups.at(-1).messages.pop()], type: '', collapsed: true })
						forceNextGroup = true
					}
					groups.at(-1).messages.push(message)
					groups.at(-1).type = groupingType
				}
				lastMessage = message
			}
			return groups
		},

		checkIfSelfIsActor(message) {
			return message.messageParameters.actor.id === this.$store.getters.getActorId()
				&& message.messageParameters.actor.type + 's' === this.$store.getters.getActorType()
		},

		checkIfSelfIsUser(message) {
			return message.messageParameters.user.id === this.$store.getters.getActorId()
				&& message.messageParameters.user.type + 's' === this.$store.getters.getActorType()
		},

		createCombinedSystemMessage({ messages, type }) {
			const combinedMessage = cloneDeep(messages[0])

			// Handle cases when users reconnected to the call
			if (type === 'call_reconnected') {
				if (this.checkIfSelfIsActor(combinedMessage)) {
					combinedMessage.message = t('spreed', 'You reconnected to the call')
				} else {
					combinedMessage.message = t('spreed', '{actor} reconnected to the call')
				}

				return combinedMessage
			}

			// clear messageParameters to be filled later
			const actor = messages[0].messageParameters.actor
			combinedMessage.messageParameters = { actor }
			const actorIsAdministrator = actor.id === 'guest/cli' && actor.type === 'guest'

			// usersCounter should be equal at least 2, as we're using this method only for groups
			let usersCounter = 0
			let selfIsUser = false
			let referenceIndex = 0

			// Handle cases when actor added users to conversation (when populate on creation, for example)
			if (type === 'user_added') {
				const selfIsActor = combinedMessage.actorId === this.$store.getters.getActorId()
					&& combinedMessage.actorType === this.$store.getters.getActorType()
				messages.forEach(message => {
					if (this.checkIfSelfIsUser(message)) {
						selfIsUser = true
					} else {
						combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.user
						referenceIndex++
					}
					usersCounter++
				})

				if (selfIsActor) {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', 'You added {user0} and {user1}')
					} else {
						combinedMessage.message = n('spreed',
							'You added {user0}, {user1} and %n more participant',
							'You added {user0}, {user1} and %n more participants', usersCounter - 2)
					}
				} else if (selfIsUser) {
					if (usersCounter === 2) {
						combinedMessage.message = actorIsAdministrator
							? t('spreed', 'An administrator added you and {user0}')
							: t('spreed', '{actor} added you and {user0}')
					} else {
						combinedMessage.message = actorIsAdministrator
							? n('spreed',
								'An administrator added you, {user0} and %n more participant',
								'An administrator added you, {user0} and %n more participants', usersCounter - 2)
							: n('spreed',
								'{actor} added you, {user0} and %n more participant',
								'{actor} added you, {user0} and %n more participants', usersCounter - 2)
					}
				} else {
					if (usersCounter === 2) {
						combinedMessage.message = actorIsAdministrator
							? t('spreed', 'An administrator added {user0} and {user1}')
							: t('spreed', '{actor} added {user0} and {user1}')
					} else {
						combinedMessage.message = actorIsAdministrator
							? n('spreed',
								'An administrator added {user0}, {user1} and %n more participant',
								'An administrator added {user0}, {user1} and %n more participants', usersCounter - 2)
							: n('spreed',
								'{actor} added {user0}, {user1} and %n more participant',
								'{actor} added {user0}, {user1} and %n more participants', usersCounter - 2)
					}
				}
			}

			// Used to hide duplicates from system message headers,
			// when the same user joins or leaves call several times
			const storedUniqueUsers = []

			// Handle cases when users joined the call
			if (type === 'call_joined') {
				messages.forEach(message => {
					const actorReference = `${message.messageParameters.actor.id}_${message.messageParameters.actor.type}`
					if (storedUniqueUsers.includes(actorReference)) {
						return
					}
					if (this.checkIfSelfIsActor(message)) {
						selfIsUser = true
					} else {
						combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.actor
						storedUniqueUsers.push(actorReference)
						referenceIndex++
					}
					usersCounter++
				})

				if (usersCounter === 1) {
					combinedMessage.message = messages[0].message
				} else if (selfIsUser) {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', 'You and {user0} joined the call')
					} else {
						combinedMessage.message = n('spreed',
							'You, {user0} and %n more participant joined the call',
							'You, {user0} and %n more participants joined the call', usersCounter - 2)
					}
				} else {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', '{user0} and {user1} joined the call')
					} else {
						combinedMessage.message = n('spreed',
							'{user0}, {user1} and %n more participant joined the call',
							'{user0}, {user1} and %n more participants joined the call', usersCounter - 2)
					}
				}
			}

			// Handle cases when users left the call
			if (type === 'call_left') {
				messages.forEach(message => {
					const actorReference = `${message.messageParameters.actor.id}_${message.messageParameters.actor.type}`
					if (storedUniqueUsers.includes(actorReference)) {
						return
					}
					if (this.checkIfSelfIsActor(message)) {
						selfIsUser = true
					} else {
						combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.actor
						storedUniqueUsers.push(actorReference)
						referenceIndex++
					}
					usersCounter++
				})

				if (usersCounter === 1) {
					combinedMessage.message = messages[0].message
				} else if (selfIsUser) {
					 if (usersCounter === 2) {
						combinedMessage.message = t('spreed', 'You and {user0} left the call')
					} else {
						combinedMessage.message = n('spreed',
							'You, {user0} and %n more participant left the call',
							'You, {user0} and %n more participants left the call', usersCounter - 2)
					}
				} else {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', '{user0} and {user1} left the call')
					} else {
						combinedMessage.message = n('spreed',
							'{user0}, {user1} and %n more participant left the call',
							'{user0}, {user1} and %n more participants left the call', usersCounter - 2)
					}
				}
			}

			// Handle cases when actor promoted several users to moderators
			if (type === 'moderator_promoted') {
				const selfIsActor = combinedMessage.actorId === this.$store.getters.getActorId()
					&& combinedMessage.actorType === this.$store.getters.getActorType()
				messages.forEach(message => {
					if (this.checkIfSelfIsUser(message)) {
						selfIsUser = true
					} else {
						combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.user
						referenceIndex++
					}
					usersCounter++
				})

				if (selfIsActor) {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', 'You promoted {user0} and {user1} to moderators')
					} else {
						combinedMessage.message = n('spreed',
							'You promoted {user0}, {user1} and %n more participant to moderators',
							'You promoted {user0}, {user1} and %n more participants to moderators', usersCounter - 2)
					}
				} else if (selfIsUser) {
					if (usersCounter === 2) {
						combinedMessage.message = actorIsAdministrator
							? t('spreed', 'An administrator promoted you and {user0} to moderators')
							: t('spreed', '{actor} promoted you and {user0} to moderators')
					} else {
						combinedMessage.message = actorIsAdministrator
							? n('spreed',
								'An administrator promoted you, {user0} and %n more participant to moderators',
								'An administrator promoted you, {user0} and %n more participants to moderators', usersCounter - 2)
							: n('spreed',
								'{actor} promoted you, {user0} and %n more participant to moderators',
								'{actor} promoted you, {user0} and %n more participants to moderators', usersCounter - 2)
					}
				} else {
					if (usersCounter === 2) {
						combinedMessage.message = actorIsAdministrator
							? t('spreed', 'An administrator promoted {user0} and {user1} to moderators')
							: t('spreed', '{actor} promoted {user0} and {user1} to moderators')
					} else {
						combinedMessage.message = actorIsAdministrator
							? n('spreed',
								'An administrator promoted {user0}, {user1} and %n more participant to moderators',
								'An administrator promoted {user0}, {user1} and %n more participants to moderators', usersCounter - 2)
							: n('spreed',
								'{actor} promoted {user0}, {user1} and %n more participant to moderators',
								'{actor} promoted {user0}, {user1} and %n more participants to moderators', usersCounter - 2)
					}
				}
			}

			// Handle cases when actor demoted several users from moderators
			if (type === 'moderator_demoted') {
				const selfIsActor = combinedMessage.actorId === this.$store.getters.getActorId()
					&& combinedMessage.actorType === this.$store.getters.getActorType()
				messages.forEach(message => {
					if (this.checkIfSelfIsUser(message)) {
						selfIsUser = true
					} else {
						combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.user
						referenceIndex++
					}
					usersCounter++
				})

				if (selfIsActor) {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', 'You demoted {user0} and {user1} from moderators')
					} else {
						combinedMessage.message = n('spreed',
							'You demoted {user0}, {user1} and %n more participant from moderators',
							'You demoted {user0}, {user1} and %n more participants from moderators', usersCounter - 2)
					}
				} else if (selfIsUser) {
					if (usersCounter === 2) {
						combinedMessage.message = actorIsAdministrator
							? t('spreed', 'An administrator demoted you and {user0} from moderators')
							: t('spreed', '{actor} demoted you and {user0} from moderators')
					} else {
						combinedMessage.message = actorIsAdministrator
							? n('spreed',
								'An administrator demoted you, {user0} and %n more participant from moderators',
								'An administrator demoted you, {user0} and %n more participants from moderators', usersCounter - 2)
							: n('spreed',
								'{actor} demoted you, {user0} and %n more participant from moderators',
								'{actor} demoted you, {user0} and %n more participants from moderators', usersCounter - 2)
					}
				} else {
					if (usersCounter === 2) {
						combinedMessage.message = actorIsAdministrator
							? t('spreed', 'An administrator demoted {user0} and {user1} from moderators')
							: t('spreed', '{actor} demoted {user0} and {user1} from moderators')
					} else {
						combinedMessage.message = actorIsAdministrator
							? n('spreed',
								'An administrator demoted {user0}, {user1} and %n more participant from moderators',
								'An administrator demoted {user0}, {user1} and %n more participants from moderators', usersCounter - 2)
							: n('spreed',
								'{actor} demoted {user0}, {user1} and %n more participant from moderators',
								'{actor} demoted {user0}, {user1} and %n more participants from moderators', usersCounter - 2)
					}
				}
			}

			return combinedMessage
		},

		toggleCollapsed(messages) {
			this.$set(messages, 'collapsed', !messages.collapsed)
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

.message-group {
	&__date-header {
		display: block;
		text-align: center;
		padding-top: 20px;
		position: relative;
		margin: 20px 0;
		.date {
			margin-right: $clickable-area * 2;
			content: attr(data-date);
			padding: 4px 12px;
			left: 50%;
			color: var(--color-text-maxcontrast);
			background-color: var(--color-background-dark);
			border-radius: var(--border-radius-pill);
		}
	}

	&__system {
		display: flex;
		flex-direction: column;
	}
}

.wrapper {
	max-width: $messages-list-max-width;
	display: flex;
	margin: auto;
	padding: 0;
	&--system {
		flex-direction: column;
		padding-left: $clickable-area + 8px;
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
		position: relative;
		& &__toggle {
			position: absolute;
			top: 50%;
			right: 0;
			transform: translateY(-50%);
		}
	}
	&--collapsed {
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-hover);
	}
}
</style>
