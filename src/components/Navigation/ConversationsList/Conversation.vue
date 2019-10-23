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
		:title="conversationName"
		:to="{ name: 'conversation', params: { token: item.token }}"
		@click.prevent.exact="joinConversation(item.token)">
		<ConversationIcon
			slot="icon"
			:item="item" />
		<template slot="subtitle">
			{{ item.lastMessage.message }}
		</template>
		<AppNavigationCounter v-if="item.unreadMessages"
			slot="counter"
			:highlighted="true">
			{{ item.unreadMessages }}
		</AppNavigationCounter>
		<template slot="actions">
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

			<!-- FIXME Should be a real separator -->
			<ActionText
				icon="icon-more">
				------
			</ActionText>

			<ActionText
				icon="icon-timezone">
				{{ t('spreed', 'Chat notifications') }}
			</ActionText>
			<ActionButton
				icon="icon-sound"
				@click.prevent.exact="setNotificationLevel(1)">
				{{ t('spreed', 'All messages') }}
			</ActionButton>
			<ActionButton
				icon="icon-user"
				@click.prevent.exact="setNotificationLevel(2)">
				{{ t('spreed', '@-mentions only') }}
			</ActionButton>
			<ActionButton
				icon="icon-sound-off"
				@click.prevent.exact="setNotificationLevel(3)">
				{{ t('spreed', 'Off') }}
			</ActionButton>

			<!-- FIXME Should be a real separator -->
			<ActionText
				icon="icon-more">
				------
			</ActionText>

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
import ConversationIcon from './../../ConversationIcon'
import AppNavigationCounter from 'nextcloud-vue/dist/Components/AppNavigationCounter'
import AppContentListItem from './AppContentListItem/AppContentListItem'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import ActionText from 'nextcloud-vue/dist/Components/ActionText'
import { joinConversation, removeCurrentUserFromConversation } from '../../../services/participantsService'
import { deleteConversation, addToFavorites, removeFromFavorites, setNotificationLevel } from '../../../services/conversationsService'
import { generateUrl } from 'nextcloud-router'
import { CONVERSATION, PARTICIPANT } from '../../../constants'

export default {
	name: 'Conversation',
	components: {
		ActionButton,
		ActionText,
		AppContentListItem,
		AppNavigationCounter,
		ConversationIcon
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
					notificationLevel: 0
				}
			}
		}
	},
	computed: {
		conversationName() {
			// FIXME this is just for demonstration, instead the yellow star
			// FIXME should be added on top of the ConversationIcon
			return (this.item.isFavorite ? 'FAVORITE' : '') + this.item.displayName
		},
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
		}
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
		}
	}
}
</script>

<style lang="scss" scoped>
.scroller {
	flex: 1 0;
}

.ellipsis {
	text-overflow: ellipsis;
}
</style>
