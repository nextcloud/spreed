<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  -
  - @license GNU AGPL version 3 or any later version
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
	<ListItem :title="item.displayName"
		:anchor-id="`conversation_${item.token}`"
		:active="isActive"
		:to="to"
		:bold="!!item.unreadMessages"
		:counter-number="item.unreadMessages"
		:counter-type="counterType"
		@click="onClick">
		<template #icon>
			<ConversationIcon :item="item"
				:hide-favorite="false"
				:hide-call="false"
				:disable-menu="true" />
		</template>
		<template #subtitle>
			<strong v-if="item.unreadMessages">
				{{ conversationInformation }}
			</strong>
			<template v-else>
				{{ conversationInformation }}
			</template>
		</template>
		<template v-if="!isSearchResult" slot="actions">
			<ActionButton v-if="canFavorite"
				:icon="iconFavorite"
				@click.prevent.exact="toggleFavoriteConversation">
				{{ labelFavorite }}
			</ActionButton>
			<ActionButton icon="icon-clippy"
				@click.stop.prevent="copyLinkToConversation">
				{{ t('spreed', 'Copy link') }}
			</ActionButton>
			<ActionButton :close-after-click="true"
				@click.prevent.exact="markConversationAsRead">
				<template #icon>
					<EyeOutline decorative
						title=""
						:size="16" />
				</template>
				{{ t('spreed', 'Mark as read') }}
			</ActionButton>
			<ActionButton icon="icon-settings"
				:close-after-click="true"
				@click.prevent.exact="showConversationSettings">
				{{ t('spreed', 'Conversation settings') }}
			</ActionButton>
			<ActionButton v-if="canLeaveConversation"
				:close-after-click="true"
				:icon="iconLeaveConversation"
				@click.prevent.exact="leaveConversation">
				{{ t('spreed', 'Leave conversation') }}
			</ActionButton>
			<ActionButton v-if="canDeleteConversation"
				:close-after-click="true"
				icon="icon-delete-critical"
				class="critical"
				@click.prevent.exact="deleteConversation">
				{{ t('spreed', 'Delete conversation') }}
			</ActionButton>
		</template>
	</ListItem>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import EyeOutline from 'vue-material-design-icons/EyeOutline'
import ConversationIcon from './../../ConversationIcon'
import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'
import { CONVERSATION, PARTICIPANT, ATTENDEE } from '../../../constants'
import ListItem from '@nextcloud/vue/dist/Components/ListItem'

export default {
	name: 'Conversation',
	components: {
		ActionButton,
		ListItem,
		ConversationIcon,
		EyeOutline,
	},
	props: {
		isSearchResult: {
			type: Boolean,
			default: false,
		},
		item: {
			type: Object,
			default() {
				return {
					token: '',
					participants: [],
					participantType: 0,
					unreadMessages: 0,
					unreadMention: false,
					objectType: '',
					type: 0,
					displayName: '',
					isFavorite: false,
					notificationLevel: 0,
					lastMessage: {},
				}
			},
		},
	},

	computed: {

		counterType() {
			if (this.item.unreadMentionDirect || (this.item.unreadMessages !== 0 && this.item.type === CONVERSATION.TYPE.ONE_TO_ONE)) {
				return 'highlighted'
			} else if (this.item.unreadMention) {
				return 'outlined'
			} else {
				return ''
			}
		},

		linkToConversation() {
			return window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.item.token)
		},

		canFavorite() {
			return this.item.participantType !== PARTICIPANT.TYPE.USER_SELF_JOINED
		},

		iconFavorite() {
			return this.item.isFavorite ? 'icon-favorite' : 'icon-starred'
		},

		labelFavorite() {
			return this.item.isFavorite ? t('spreed', 'Remove from favorites') : t('spreed', 'Add to favorites')
		},

		isNotifyAlways() {
			return this.item.notificationLevel === PARTICIPANT.NOTIFY.ALWAYS
		},

		isNotifyMention() {
			return this.item.notificationLevel === PARTICIPANT.NOTIFY.MENTION
		},

		isNotifyNever() {
			return this.item.notificationLevel === PARTICIPANT.NOTIFY.NEVER
		},

		canDeleteConversation() {
			return this.item.canDeleteConversation
		},

		canLeaveConversation() {
			return this.item.canLeaveConversation
		},

		iconLeaveConversation() {
			if (this.canDeleteConversation) {
				return 'icon-close'
			}
			return 'icon-delete'
		},

		conversationInformation() {
			// temporary item while joining
			if (!this.isSearchResult && !this.item.actorId) {
				return t('spreed', 'Joining conversation …')
			}

			if (!Object.keys(this.lastChatMessage).length) {
				return ''
			}

			if (this.shortLastChatMessageAuthor === '') {
				return this.simpleLastChatMessage
			}

			if (this.lastChatMessage.actorId === this.$store.getters.getUserId()) {
				return t('spreed', 'You: {lastMessage}', {
					lastMessage: this.simpleLastChatMessage,
				}, undefined, {
					escape: false,
					sanitize: false,
				})
			}

			if (this.item.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.item.type === CONVERSATION.TYPE.CHANGELOG) {
				return this.simpleLastChatMessage
			}

			return t('spreed', '{actor}: {lastMessage}', {
				actor: this.shortLastChatMessageAuthor,
				lastMessage: this.simpleLastChatMessage,
			}, undefined, {
				escape: false,
				sanitize: false,
			})
		},

		// The messages array for this conversation
		messages() {
			return this.$store.getters.messages(this.item.token)
		},

		// Get the last message for this conversation from the message store instead
		// of the conversations store. The message store is updated immediately,
		// while the conversations store is refreshed every 30 seconds. This allows
		// to display message previews in this component as soon as new messages are
		// received by the server.
		lastChatMessage() {
			const lastMessageTimestamp = this.item.lastMessage ? this.item.lastMessage.timestamp : 0

			if (Object.keys(this.messages).length > 0) {
				// FIXME: risky way to get last message that assumes that keys are always sorted
				// should use this.$store.getters.messagesList instead ?
				const messagesKeys = Object.keys(this.messages)
				const lastMessageId = messagesKeys[messagesKeys.length - 1]

				/**
				 * Only use the last message as lastmessage when:
				 * 1. It's newer than the conversations last message
				 * 2. It's not a command reply
				 * 3. It's not a temporary message starting with "/" which is a user posting a command
				 */
				if (this.messages[lastMessageId].timestamp > lastMessageTimestamp
					&& (this.messages[lastMessageId].actorType !== ATTENDEE.ACTOR_TYPE.BOTS
						|| this.messages[lastMessageId].actorId === ATTENDEE.CHANGELOG_BOT_ID)
					&& (!lastMessageId.startsWith('temp-')
						|| !this.messages[lastMessageId].message.startsWith('/'))) {
					return this.messages[lastMessageId]
				}
			}
			return this.item.lastMessage
		},

		/**
		 * This is a simplified version of the last chat message.
		 * Parameters are parsed without markup (just replaced with the name),
		 * e.g. no avatars on mentions.
		 *
		 * @return {string} A simple message to show below the conversation name
		 */
		simpleLastChatMessage() {
			if (!Object.keys(this.lastChatMessage).length) {
				return ''
			}

			const params = this.lastChatMessage.messageParameters
			let subtitle = this.lastChatMessage.message.trim()

			// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
			Object.keys(params).forEach((parameterKey) => {
				subtitle = subtitle.replace('{' + parameterKey + '}', params[parameterKey].name)
			})

			return subtitle
		},

		/**
		 * @return {string} Part of the name until the first space
		 */
		shortLastChatMessageAuthor() {
			if (!Object.keys(this.lastChatMessage).length
				|| this.lastChatMessage.systemMessage.length) {
				return ''
			}

			let author = this.lastChatMessage.actorDisplayName.trim()
			const spacePosition = author.indexOf(' ')
			if (spacePosition !== -1) {
				author = author.substring(0, spacePosition)
			}

			if (author.length === 0 && this.lastChatMessage.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				return t('spreed', 'Guest')
			}

			return author
		},

		to() {
			return this.item?.token ? { name: 'conversation', params: { token: this.item.token } } : ''
		},

		isActive() {
			if (!this.isSearchResult) {
				return this.$store.getters.getToken() === this.to.params.token
			} else {
				return false
			}
		},
	},
	methods: {
		async copyLinkToConversation() {
			try {
				await this.$copyText(this.linkToConversation)
				showSuccess(t('spreed', 'Conversation link copied to clipboard.'))
			} catch (error) {
				console.error('Error copying link: ', error)
				showError(t('spreed', 'The link could not be copied.'))
			}
		},

		markConversationAsRead() {
			this.$store.dispatch('clearLastReadMessage', { token: this.item.token })
		},

		showConversationSettings() {
			emit('show-conversation-settings', { token: this.item.token })
		},

		/**
		 * Deletes the conversation.
		 */
		async deleteConversation() {
			OC.dialogs.confirm(
				t('spreed', 'Do you really want to delete "{displayName}"?', this.item, undefined, {
					escape: false,
					sanitize: false,
				}),
				t('spreed', 'Delete conversation'),
				async function(decision) {
					if (!decision) {
						return
					}

					try {
						await this.$store.dispatch('deleteConversationFromServer', { token: this.item.token })
					} catch (error) {
						console.debug(`error while deleting conversation ${error}`)
						showError(t('spreed', 'Error while deleting conversation'))
					}
				}.bind(this)
			)
		},

		/**
		 * Deletes the current user from the conversation.
		 */
		async leaveConversation() {
			try {
				await this.$store.dispatch('removeCurrentUserFromConversation', { token: this.item.token })
			} catch (error) {
				if (error?.response?.status === 400) {
					showError(t('spreed', 'You need to promote a new moderator before you can leave the conversation.'))
				} else {
					console.debug(`error while removing yourself from conversation ${error}`)
				}
			}
		},
		async toggleFavoriteConversation() {
			this.$store.dispatch('toggleFavorite', this.item)
		},

		/**
		 * Set the notification level for the conversation
		 *
		 * @param {number} level The notification level to set.
		 */
		async setNotificationLevel(level) {
			await this.$store.dispatch('setNotificationLevel', { token: this.item.token, notificationLevel: level })
		},

		// forward click event
		onClick(event) {
			this.$emit('click', event)
		},
	},
}
</script>

<style lang="scss" scoped>

::v-deep .action-text__title {
	margin-left: 12px;
}

.critical {
	::v-deep .action-button__text {
		color: var(--color-error) !important;
	}
}

.scroller {
	flex: 1 0;
}

.ellipsis {
	text-overflow: ellipsis;
}

.forced-active {
	background-color: var(--color-primary-light) !important //Overrides gray hover feedback;
}
</style>
