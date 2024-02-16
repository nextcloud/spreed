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
			:title="item.displayName"
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
			<template v-if="isFederated || isPublic || isGroup" #details>
				<WebIcon v-if="isFederated" :size="16" />
				<LinkIcon v-else-if="isPublic" :size="16" />
				<AccountMultipleIcon v-else-if="isGroup" :size="16" />
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

import { toRefs } from 'vue'
import { Fragment } from 'vue-frag'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'

import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import ExitToApp from 'vue-material-design-icons/ExitToApp.vue'
import EyeOffOutline from 'vue-material-design-icons/EyeOffOutline.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import Star from 'vue-material-design-icons/Star.vue'
import WebIcon from 'vue-material-design-icons/Web.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import ConversationIcon from './../../ConversationIcon.vue'

import { useConversationInfo } from '../../../composables/useConversationInfo.js'
import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
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
		AccountMultipleIcon,
		ArrowRight,
		Cog,
		Delete,
		ExitToApp,
		EyeOffOutline,
		EyeOutline,
		LinkIcon,
		Star,
		WebIcon,
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

	setup(props) {
		const { item, isSearchResult } = toRefs(props)
		const { counterType, conversationInformation } = useConversationInfo({ item, isSearchResult })

		return {
			counterType,
			conversationInformation,
		}
	},

	data() {
		return {
			isDialogOpen: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		canFavorite() {
			return this.item.participantType !== PARTICIPANT.TYPE.USER_SELF_JOINED
		},

		isFederated() {
			return !!this.item.remoteServer
		},

		isPublic() {
			return this.item.type === CONVERSATION.TYPE.PUBLIC
		},

		isGroup() {
			return this.item.type === CONVERSATION.TYPE.GROUP
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

		dialogMessage() {
			return t('spreed', 'Do you really want to delete "{displayName}"?', this.item, undefined, {
				escape: false,
				sanitize: false,
			})
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
