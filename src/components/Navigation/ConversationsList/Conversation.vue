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
	<AppContentListItem
		:title="item.displayName"
		:to="{ name: 'conversation', params: { token: item.token }}">
		<ConversationIcon v-slot:icon
			:item="item"
			:hide-favorite="false" />
		<template v-slot:subtitle>
			{{ simpleLastChatMessage }}
		</template>
		<AppNavigationCounter v-if="item.unreadMessages"
			v-slot:counter
			class="counter"
			:highlighted="true">
			{{ item.unreadMessages }}
		</AppNavigationCounter>
		<template v-slot:actions>
			<ActionButton v-if="canFavorite"
				:icon="iconFavorite"
				@click.prevent.exact="toggleFavoriteConversation">
				{{ labelFavorite }}
			</ActionButton>
			<ActionButton
				icon="icon-clippy"
				@click.stop.prevent="copyLinkToConversation">
				{{ t('spreed', 'Copy link') }}
			</ActionButton>

			<ActionText class="separator" />

			<ActionText
				:title="t('spreed', 'Chat notifications')" />
			<ActionButton
				:class="{'forced-active': isNotifyAlways}"
				icon="icon-sound"
				@click.prevent.exact="setNotificationLevel(1)">
				{{ t('spreed', 'All messages') }}
			</ActionButton>
			<ActionButton
				:class="{'forced-active': isNotifyMention}"
				icon="icon-user"
				@click.prevent.exact="setNotificationLevel(2)">
				{{ t('spreed', '@-mentions only') }}
			</ActionButton>
			<ActionButton
				:class="{'forced-active': isNotifyNever}"
				icon="icon-sound-off"
				@click.prevent.exact="setNotificationLevel(3)">
				{{ t('spreed', 'Off') }}
			</ActionButton>

			<ActionText class="separator" />

			<ActionButton v-if="canLeaveConversation"
				:icon="iconLeaveConversation"
				@click.prevent.exact="leaveConversation">
				{{ t('spreed', 'Leave conversation') }}
			</ActionButton>
			<ActionButton v-if="canDeleteConversation"
				icon="icon-delete"
				@click.prevent.exact="deleteConversation">
				{{ t('spreed', 'Delete conversation') }}
			</ActionButton>
		</template>
	</AppContentListItem>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionText from '@nextcloud/vue/dist/Components/ActionText'
import AppContentListItem from './AppContentListItem/AppContentListItem'
import AppNavigationCounter from '@nextcloud/vue/dist/Components/AppNavigationCounter'
import ConversationIcon from './../../ConversationIcon'
import { joinConversation, removeCurrentUserFromConversation } from '../../../services/participantsService'
import { deleteConversation, addToFavorites, removeFromFavorites, setNotificationLevel } from '../../../services/conversationsService'
import { generateUrl } from '@nextcloud/router'
import { CONVERSATION, PARTICIPANT } from '../../../constants'

export default {
	name: 'Conversation',
	components: {
		ActionButton,
		ActionText,
		AppContentListItem,
		AppNavigationCounter,
		ConversationIcon,
	},
	props: {
		item: {
			type: Object,
			default: function() {
				return {
					token: '',
					participants: [],
					participantType: 0,
					unreadMessages: 0,
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
		linkToConversation() {
			return window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.item.token)
		},
		canFavorite() {
			return this.item.participantType !== PARTICIPANT.TYPE.USER_SELF_JOINED
		},
		iconFavorite() {
			return this.item.isFavorite ? 'icon-star-dark' : 'icon-starred'
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
			return this.item.type !== CONVERSATION.TYPE.ONE_TO_ONE && (this.item.participantType === PARTICIPANT.TYPE.OWNER || this.item.participantType === PARTICIPANT.TYPE.MODERATOR)
		},
		canLeaveConversation() {
			return !this.canDeleteConversation || (this.item.type !== CONVERSATION.TYPE.ONE_TO_ONE && Object.keys(this.item.participants).length > 1)
		},
		iconLeaveConversation() {
			if (this.canDeleteConversation) {
				return 'icon-close'
			}
			return 'icon-delete'
		},
		/**
		 * This is a simplified version of the last chat message.
		 * Parameters are parsed without markup (just replaced with the name),
		 * e.g. no avatars on mentions.
		 * @returns {string} A simple message to show below the conversation name
		 */
		simpleLastChatMessage() {
			if (!Object.keys(this.item.lastMessage).length) {
				return ''
			}

			const params = this.item.lastMessage.messageParameters
			let subtitle = this.item.lastMessage.message

			// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
			Object.keys(params).forEach((parameterKey) => {
				subtitle = subtitle.replace('{' + parameterKey + '}', params[parameterKey].name)
			})

			return subtitle
		},
	},
	methods: {
		async copyLinkToConversation() {
			try {
				await this.$copyText(this.linkToConversation)
				OCP.Toast.success(t('spreed', 'Link to conversation copied to clipboard'))
			} catch (error) {
				OCP.Toast.error(t('spreed', 'Link to conversation was not copied to clipboard.'))
			}
		},
		async joinConversation() {
			await joinConversation(this.item.token)
		},
		/**
		 * Deletes the conversation.
		 */
		async deleteConversation() {
			try {
				await deleteConversation(this.item.token)
				// If successful, deletes the conversation from the store
				this.$store.dispatch('deleteConversation', this.item)
			} catch (error) {
				console.debug(`error while deleting conversation ${error}`)
			}
		},
		/**
		 * Deletes the current user from the conversation.
		 */
		async leaveConversation() {
			try {
				await removeCurrentUserFromConversation(this.item.token)
				// If successful, deletes the conversation from the store
				this.$store.dispatch('deleteConversation', this.item)
			} catch (error) {
				console.debug(`error while removing yourself from conversation ${error}`)
			}
		},
		async toggleFavoriteConversation() {
			if (this.item.isFavorite) {
				await removeFromFavorites(this.item.token)
			} else {
				await addToFavorites(this.item.token)
			}

			this.item.isFavorite = !this.item.isFavorite
		},
		/**
		 * Set the notification level for the conversation
		 * @param {int} level The notification level to set.
		 */
		async setNotificationLevel(level) {
			await setNotificationLevel(this.item.token, level)
			this.item.notificationLevel = level
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .counter {
	line-height: inherit;
	font-size: 12px;

	span {
		padding: 2px 6px;
	}
}
.scroller {
	flex: 1 0;
}

.ellipsis {
	text-overflow: ellipsis;
}

.separator {
	height: 0;
	margin: 5px 10px 5px 15px;
	border-bottom: 1px solid var(--color-border-dark);
}

.forced-active {
	box-shadow: inset 4px 0 var(--color-primary);
}
</style>
