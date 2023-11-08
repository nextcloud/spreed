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
	<Fragment>
		<NcListItem ref="listItem"
			:key="item.token"
			:name="item.displayName"
			class="conversation-item"
			:class="{'unread-mention-conversation': item.unreadMention}"
			:data-nav-id="`conversation_${item.token}`"
			:actions-aria-label="t('spreed', 'Conversation actions')"
			:to="to"
			:bold="!!item.unreadMessages"
			:counter-number="item.unreadMessages"
			:counter-type="counterType"
			@click="onClick">
			<template #icon>
				<ConversationIcon :item="item" :hide-favorite="false" :hide-call="false" />
			</template>
			<template #subname>
				<strong v-if="item.unreadMessages"
					class="subtitle">
					{{ conversationInformation }}
				</strong>
				<template v-else>
					{{ conversationInformation }}
				</template>
			</template>
			<template v-if="!isSearchResult" #actions>
				<NcActionButton v-if="canFavorite"
					:close-after-click="true"
					@click="toggleFavoriteConversation">
					<template #icon>
						<Star v-if="item.isFavorite" :size="20" />
						<Star v-else :size="20" :fill-color="'#FFCC00'" />
					</template>
					{{ labelFavorite }}
				</NcActionButton>
				<NcActionButton icon="icon-clippy"
					@click.stop="handleCopyLink">
					{{ t('spreed', 'Copy link') }}
				</NcActionButton>
				<NcActionButton v-if="item.unreadMessages"
					:close-after-click="true"
					@click="markConversationAsRead">
					<template #icon>
						<EyeOutline :size="16" />
					</template>
					{{ t('spreed', 'Mark as read') }}
				</NcActionButton>
				<NcActionButton v-else
					:close-after-click="true"
					@click="markConversationAsUnread">
					<template #icon>
						<EyeOffOutline :size="16" />
					</template>
					{{ t('spreed', 'Mark as unread') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true"
					@click="showConversationSettings">
					<template #icon>
						<Cog :size="20" />
					</template>
					{{ t('spreed', 'Conversation settings') }}
				</NcActionButton>
				<NcActionButton v-if="canLeaveConversation"
					:close-after-click="true"
					@click="leaveConversation">
					<template #icon>
						<ExitToApp :size="16" />
					</template>
					{{ t('spreed', 'Leave conversation') }}
				</NcActionButton>
				<NcActionButton v-if="canDeleteConversation"
					:close-after-click="true"
					class="critical"
					@click="showDialog">
					<template #icon>
						<Delete :size="16" />
					</template>
					{{ t('spreed', 'Delete conversation') }}
				</NcActionButton>
			</template>
			<template v-else-if="item.token" #actions>
				<NcActionButton close-after-click @click="onActionClick">
					<template #icon>
						<ArrowRight :size="16" />
					</template>
					{{ t('spreed', 'Join conversation') }}
				</NcActionButton>
				<NcActionButton icon="icon-clippy"
					@click.stop="handleCopyLink">
					{{ t('spreed', 'Copy link') }}
				</NcActionButton>
			</template>
		</NcListItem>
		<!-- confirmation required to delete conversation -->
		<NcDialog :open.sync="isDialogOpen"
			:name="t('spreed','Delete Conversation')"
			:message="dialogMessage"
			:container="container">
			<template #actions>
				<NcButton type="tertiary" @click="closeDialog">
					{{ t('spreed', 'No') }}
				</NcButton>
				<NcButton type="error" @click="deleteConversation">
					{{ t('spreed', 'Yes') }}
				</NcButton>
			</template>
		</NcDialog>
	</Fragment>
</template>

<script>

import { Fragment } from 'vue-frag'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'

import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import ExitToApp from 'vue-material-design-icons/ExitToApp.vue'
import EyeOffOutline from 'vue-material-design-icons/EyeOffOutline.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import Star from 'vue-material-design-icons/Star.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import ConversationIcon from './../../ConversationIcon.vue'

import { CONVERSATION, PARTICIPANT, ATTENDEE } from '../../../constants.js'
import { copyConversationLinkToClipboard } from '../../../services/urlService.js'

export default {
	name: 'Conversation',

	components: {
		NcButton,
		ConversationIcon,
		NcActionButton,
		NcDialog,
		NcListItem,
		Fragment,
		// Icons
		ArrowRight,
		Cog,
		Delete,
		ExitToApp,
		EyeOffOutline,
		EyeOutline,
		Star,
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

	emits: ['click'],

	data() {
		return {
			isDialogOpen: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		counterType() {
			if (this.item.unreadMentionDirect || (this.item.unreadMessages !== 0 && (
				this.item.type === CONVERSATION.TYPE.ONE_TO_ONE || this.item.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
			))) {
				return 'highlighted'
			} else if (this.item.unreadMention) {
				return 'outlined'
			} else {
				return ''
			}
		},

		canFavorite() {
			return this.item.participantType !== PARTICIPANT.TYPE.USER_SELF_JOINED
		},

		labelFavorite() {
			return this.item.isFavorite ? t('spreed', 'Remove from favorites') : t('spreed', 'Add to favorites')
		},

		canDeleteConversation() {
			return this.item.canDeleteConversation
		},

		canLeaveConversation() {
			return this.item.canLeaveConversation
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
				|| this.item.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
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

		// Get the last message for this conversation from the message store instead
		// of the conversations store. The message store is updated immediately,
		// while the conversations store is refreshed every 30 seconds. This allows
		// to display message previews in this component as soon as new messages are
		// received by the server.
		lastChatMessage() {
			return this.item.lastMessage
		},

		dialogMessage() {
			return t('spreed', 'Do you really want to delete "{displayName}"?', this.item, undefined, {
				escape: false,
				sanitize: false,
			})
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
			return this.item?.token
				? {
					name: 'conversation',
					params: { token: this.item.token },
				}
				: ''
		},
	},

	// TODO: move the implementation to @nextcloud-vue/NcListItem
	watch: {
		'item.displayName': {
			immediate: true,
			handler(value) {
				this.$nextTick().then(() => {
					const titleSpan = this.$refs.listItem?.$el?.querySelector('.line-one__name')

					if (titleSpan && titleSpan.offsetWidth < titleSpan.scrollWidth) {
						titleSpan.setAttribute('title', value)
					}
				})
			},
		},
	},

	methods: {
		handleCopyLink() {
			copyConversationLinkToClipboard(this.item.token)
		},

		markConversationAsRead() {
			this.$store.dispatch('clearLastReadMessage', { token: this.item.token })
		},

		markConversationAsUnread() {
			this.$store.dispatch('markConversationUnread', { token: this.item.token })
		},

		showConversationSettings() {
			emit('show-conversation-settings', { token: this.item.token })
		},

		/**
		 * Deletes the conversation.
		 */
		async deleteConversation() {
			try {
				this.isDialogOpen = false
				await this.$store.dispatch('deleteConversationFromServer', { token: this.item.token })
				await this.$store.dispatch('leaveConversation', { token: this.item.token })
				await this.$router.push({ name: 'root' })
					.catch((failure) => !isNavigationFailure(failure, NavigationFailureType.duplicated) && Promise.reject(failure))
			} catch (error) {
				console.debug(`error while deleting conversation ${error}`)
				showError(t('spreed', 'Error while deleting conversation'))
			}
		},

		/**
		 * Deletes the current user from the conversation.
		 */
		async leaveConversation() {
			try {
				await this.$store.dispatch('removeCurrentUserFromConversation', { token: this.item.token })
				await this.$store.dispatch('leaveConversation', { token: this.item.token })
				await this.$router.push({ name: 'root' })
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
			await this.$store.dispatch('setNotificationLevel', {
				token: this.item.token,
				notificationLevel: level,
			})
		},

		onClick() {
			// add as temporary item that will refresh after the joining process is complete
			if (this.isSearchResult) {
				this.$store.dispatch('addConversation', this.item)
			}
			this.$emit('click')
		},

		onActionClick() {
			this.onClick()
			// NcActionButton is not a RouterLink, so we should route user manually
			this.$router.push({
				name: 'conversation',
				params: { token: this.item.token },
			}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		showDialog() {
			this.isDialogOpen = true
		},

		closeDialog() {
			this.isDialogOpen = false
		}
	},
}
</script>

<style lang="scss" scoped>
.conversation-item {
	padding-left: 2px;
	padding-right: 2px;
}

.subtitle {
	font-weight: bold;
}

.critical {
	:deep(.action-button) {
		color: var(--color-error) !important;
	}
}

:deep(.dialog) {
	padding-block: 0 8px;
	padding-inline: 12px 8px;
}

</style>
