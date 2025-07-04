<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem ref="listItem"
		:key="item.token"
		:name="item.displayName"
		:title="item.displayName"
		:data-nav-id="`conversation_${item.token}`"
		:class="['conversation', { 'conversation--active': isActive }]"
		:actions-aria-label="t('spreed', 'Conversation actions')"
		:to="to"
		:bold="!!item.unreadMessages"
		:counter-number="item.unreadMessages"
		:counter-type="counterType"
		force-menu
		@click="onClick">
		<template #icon>
			<ConversationIcon :item="item" :hide-favorite="false" :hide-call="false" />
		</template>
		<template #subname>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<span v-html="conversationInformation" />
		</template>
		<template v-if="!isSearchResult" #actions>
			<template v-if="submenu === null">
				<NcActionButton v-if="canFavorite"
					key="toggle-favorite"
					close-after-click
					@click="toggleFavoriteConversation">
					<template #icon>
						<IconStar :size="16" :fill-color="!item.isFavorite ? '#FFCC00' : undefined" />
					</template>
					{{ labelFavorite }}
				</NcActionButton>

				<NcActionButton key="copy-link" @click.stop="handleCopyLink">
					<template #icon>
						<IconContentCopy :size="16" />
					</template>
					{{ t('spreed', 'Copy link') }}
				</NcActionButton>

				<NcActionButton key="toggle-read" close-after-click @click="toggleReadConversation">
					<template #icon>
						<IconEye v-if="item.unreadMessages" :size="16" />
						<IconEyeOff v-else :size="16" />
					</template>
					{{ labelRead }}
				</NcActionButton>

				<NcActionButton key="show-notifications"
					is-menu
					@click="submenu = 'notifications'">
					<template #icon>
						<IconBell :size="16" />
					</template>
					{{ t('spreed', 'Notifications') }}
				</NcActionButton>

				<NcActionButton key="show-settings" close-after-click @click="showConversationSettings">
					<template #icon>
						<IconCog :size="16" />
					</template>
					{{ t('spreed', 'Conversation settings') }}
				</NcActionButton>

				<NcActionButton v-if="supportsArchive"
					key="toggle-archive"
					close-after-click
					@click="toggleArchiveConversation">
					<template #icon>
						<IconArchive v-if="!item.isArchived" :size="16" />
						<IconArchiveOff v-else :size="16" />
					</template>
					{{ labelArchive }}
				</NcActionButton>

				<NcActionButton v-if="item.canLeaveConversation"
					key="leave-conversation"
					close-after-click
					@click="isLeaveDialogOpen = true">
					<template #icon>
						<IconExitToApp :size="16" />
					</template>
					{{ t('spreed', 'Leave conversation') }}
				</NcActionButton>

				<NcActionButton v-if="item.canDeleteConversation"
					key="delete-conversation"
					close-after-click
					class="critical"
					@click="isDeleteDialogOpen = true">
					<template #icon>
						<IconDelete :size="16" />
					</template>
					{{ t('spreed', 'Delete conversation') }}
				</NcActionButton>
			</template>
			<template v-else-if="submenu === 'notifications'">
				<NcActionButton :aria-label="t('spreed', 'Back')"
					@click.stop="submenu = null">
					<template #icon>
						<IconArrowLeft :size="16" />
					</template>
					{{ t('spreed', 'Back') }}
				</NcActionButton>

				<NcActionSeparator />

				<NcActionButton v-for="level in notificationLevels"
					:key="level.value"
					:model-value="notificationLevel"
					:value="level.value.toString()"
					type="radio"
					@click="setNotificationLevel(level.value)">
					<template #icon>
						<component :is="notificationLevelIcon(level.value)" :size="16" />
					</template>
					{{ level.label }}
				</NcActionButton>

				<template v-if="showCallNotificationSettings">
					<NcActionSeparator />

					<NcActionButton type="checkbox"
						:model-value="notificationCalls"
						@click="setNotificationCalls(!notificationCalls)">
						<template #icon>
							<IconPhoneRing :size="16" />
						</template>
						{{ t('spreed', 'Notify about calls') }}
					</NcActionButton>
				</template>
			</template>
		</template>

		<template v-else-if="item.token" #actions>
			<NcActionButton key="join-conversation" close-after-click @click="onActionClick">
				<template #icon>
					<IconArrowRight :size="16" />
				</template>
				{{ t('spreed', 'Join conversation') }}
			</NcActionButton>

			<NcActionButton key="copy-link" @click.stop="handleCopyLink">
				<template #icon>
					<IconContentCopy :size="16" />
				</template>
				{{ t('spreed', 'Copy link') }}
			</NcActionButton>
		</template>

		<!-- confirmation required to leave / delete conversation -->
		<template v-if="isLeaveDialogOpen || isDeleteDialogOpen" #extra>
			<NcDialog v-if="isLeaveDialogOpen"
				:open.sync="isLeaveDialogOpen"
				:name="t('spreed', 'Leave conversation')">
				<template #default>
					<p>{{ dialogLeaveMessage }}</p>
					<p v-if="supportsArchive && !item.isArchived">
						{{ t('spreed', 'You can archive this conversation instead.') }}
					</p>
				</template>
				<template #actions>
					<NcButton type="tertiary" @click="isLeaveDialogOpen = false">
						{{ t('spreed', 'No') }}
					</NcButton>
					<NcButton v-if="supportsArchive && !item.isArchived" type="secondary" @click="toggleArchiveConversation">
						{{ t('spreed', 'Archive conversation') }}
					</NcButton>
					<NcButton type="warning" @click="leaveConversation">
						{{ t('spreed', 'Yes') }}
					</NcButton>
				</template>
			</NcDialog>
			<NcDialog v-if="isDeleteDialogOpen"
				:open.sync="isDeleteDialogOpen"
				:name="t('spreed','Delete conversation')"
				:message="dialogDeleteMessage">
				<template #actions>
					<NcButton type="tertiary" @click="isDeleteDialogOpen = false">
						{{ t('spreed', 'No') }}
					</NcButton>
					<NcButton type="error" @click="deleteConversation">
						{{ t('spreed', 'Yes') }}
					</NcButton>
				</template>
			</NcDialog>
		</template>
	</NcListItem>
</template>

<script>

import { toRefs, ref } from 'vue'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'

import IconAccount from 'vue-material-design-icons/Account.vue'
import IconArchive from 'vue-material-design-icons/Archive.vue'
import IconArchiveOff from 'vue-material-design-icons/ArchiveOff.vue'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import IconBell from 'vue-material-design-icons/Bell.vue'
import IconCog from 'vue-material-design-icons/Cog.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconExitToApp from 'vue-material-design-icons/ExitToApp.vue'
import IconEye from 'vue-material-design-icons/Eye.vue'
import IconEyeOff from 'vue-material-design-icons/EyeOff.vue'
import IconPhoneRing from 'vue-material-design-icons/PhoneRing.vue'
import IconStar from 'vue-material-design-icons/Star.vue'
import IconVolumeHigh from 'vue-material-design-icons/VolumeHigh.vue'
import IconVolumeOff from 'vue-material-design-icons/VolumeOff.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import ConversationIcon from './../../ConversationIcon.vue'

import { useConversationInfo } from '../../../composables/useConversationInfo.ts'
import { PARTICIPANT } from '../../../constants.js'
import { hasTalkFeature } from '../../../services/CapabilitiesManager.ts'
import { copyConversationLinkToClipboard } from '../../../utils/handleUrl.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

const notificationLevels = [
	{ value: PARTICIPANT.NOTIFY.ALWAYS, label: t('spreed', 'All messages') },
	{ value: PARTICIPANT.NOTIFY.MENTION, label: t('spreed', '@-mentions only') },
	{ value: PARTICIPANT.NOTIFY.NEVER, label: t('spreed', 'Off') },
]

export default {
	name: 'Conversation',

	components: {
		ConversationIcon,
		IconAccount,
		IconArchive,
		IconArchiveOff,
		IconArrowLeft,
		IconArrowRight,
		IconBell,
		IconCog,
		IconContentCopy,
		IconDelete,
		IconExitToApp,
		IconEye,
		IconEyeOff,
		IconPhoneRing,
		IconStar,
		IconVolumeHigh,
		IconVolumeOff,
		NcActionButton,
		NcActionSeparator,
		NcButton,
		NcDialog,
		NcListItem,
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
					lastMessage: {},
					notificationLevel: PARTICIPANT.NOTIFY.DEFAULT,
					notificationCalls: PARTICIPANT.NOTIFY_CALLS.ON,
					canDeleteConversation: false,
					canLeaveConversation: false,
				}
			},
		},
	},

	emits: ['click'],

	setup(props) {
		const submenu = ref(null)
		const isLeaveDialogOpen = ref(false)
		const isDeleteDialogOpen = ref(false)
		const { item, isSearchResult } = toRefs(props)
		const { counterType, conversationInformation } = useConversationInfo({ item, isSearchResult })

		return {
			supportsArchive,
			submenu,
			isLeaveDialogOpen,
			isDeleteDialogOpen,
			counterType,
			conversationInformation,
			notificationLevels,
		}
	},

	computed: {
		canFavorite() {
			return this.item.participantType !== PARTICIPANT.TYPE.USER_SELF_JOINED
		},

		labelRead() {
			return this.item.unreadMessages ? t('spreed', 'Mark as read') : t('spreed', 'Mark as unread')
		},

		labelFavorite() {
			return this.item.isFavorite ? t('spreed', 'Remove from favorites') : t('spreed', 'Add to favorites')
		},

		labelArchive() {
			return this.item.isArchived
				? t('spreed', 'Unarchive conversation')
				: t('spreed', 'Archive conversation')
		},

		dialogLeaveMessage() {
			return t('spreed', 'Do you really want to leave "{displayName}"?', this.item, undefined, {
				escape: false,
				sanitize: false,
			})
		},

		dialogDeleteMessage() {
			return t('spreed', 'Do you really want to delete "{displayName}"?', this.item, undefined, {
				escape: false,
				sanitize: false,
			})
		},

		to() {
			return this.item?.token
				? { name: 'conversation', params: { token: this.item.token } }
				: null
		},

		isActive() {
			return this.$route?.params?.token === this.item.token
		},

		notificationLevel() {
			return this.item.notificationLevel.toString()
		},

		notificationCalls() {
			return this.item.notificationCalls === PARTICIPANT.NOTIFY_CALLS.ON
		},

		showCallNotificationSettings() {
			return !this.item.remoteServer || hasTalkFeature(this.item.token, 'federation-v2')
		},
	},

	methods: {
		t,
		handleCopyLink() {
			copyConversationLinkToClipboard(this.item.token)
		},

		toggleReadConversation() {
			if (this.item.unreadMessages) {
				this.$store.dispatch('clearLastReadMessage', { token: this.item.token })
			} else {
				this.$store.dispatch('markConversationUnread', { token: this.item.token })
			}
		},

		showConversationSettings() {
			emit('show-conversation-settings', { token: this.item.token })
		},

		/**
		 * Deletes the conversation.
		 */
		async deleteConversation() {
			try {
				this.isDeleteDialogOpen = false
				if (this.isActive) {
					await this.$store.dispatch('leaveConversation', { token: this.item.token })
					await this.$router.push({ name: 'root' })
						.catch((failure) => !isNavigationFailure(failure, NavigationFailureType.duplicated) && Promise.reject(failure))
				}
				await this.$store.dispatch('deleteConversationFromServer', { token: this.item.token })
			} catch (error) {
				console.error(`Error while deleting conversation ${error}`)
				showError(t('spreed', 'Error while deleting conversation'))
			}
		},

		/**
		 * Deletes the current user from the conversation.
		 */
		async leaveConversation() {
			try {
				this.isLeaveDialogOpen = false
				if (this.isActive) {
					await this.$router.push({ name: 'root' })
						.catch((failure) => !isNavigationFailure(failure, NavigationFailureType.duplicated) && Promise.reject(failure))
				}
				await this.$store.dispatch('removeCurrentUserFromConversation', { token: this.item.token })
			} catch (error) {
				if (error?.response?.status === 400) {
					showError(t('spreed', 'You need to promote a new moderator before you can leave the conversation.'))
				} else {
					console.error(`Error while removing yourself from conversation ${error}`)
				}
			}
		},

		async toggleFavoriteConversation() {
			this.$store.dispatch('toggleFavorite', this.item)
		},

		async toggleArchiveConversation() {
			this.isLeaveDialogOpen = false
			this.$store.dispatch('toggleArchive', this.item)
		},

		notificationLevelIcon(value) {
			switch (value) {
			case PARTICIPANT.NOTIFY.ALWAYS:
				return IconVolumeHigh
			case PARTICIPANT.NOTIFY.MENTION:
				return IconAccount
			case PARTICIPANT.NOTIFY.NEVER:
			default:
				return IconVolumeOff
			}
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

		/**
		 * Set the call notification level for the conversation
		 *
		 * @param {boolean} value Whether or not call notifications are enabled
		 */
		async setNotificationCalls(value) {
			await this.$store.dispatch('setNotificationCalls', {
				token: this.item.token,
				notificationCalls: value ? PARTICIPANT.NOTIFY_CALLS.ON : PARTICIPANT.NOTIFY_CALLS.OFF,
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
			this.$router.push(this.to)
				.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},
	},
}
</script>

<style lang="scss" scoped>
.critical > :deep(.action-button) {
	color: var(--color-error);
}

.conversation {
	// Overwrite ConversationIcon styles to blend a type icon with NcListItem
	& :deep(.list-item:hover .conversation-icon__type) {
		background-color: var(--color-background-hover);
		border-color: var(--color-background-hover);
	}

	&--active {
		&:deep(.list-item .conversation-icon__type) {
			color: var(--color-primary-element-text);
			background-color: var(--color-primary-element);
			border-color: var(--color-primary-element);
		}

		&:deep(.list-item:hover .conversation-icon__type) {
			color: var(--color-primary-element-text);
			background-color: var(--color-primary-element-hover);
			border-color: var(--color-primary-element-hover);
		}
	}
}

:deep(.dialog) {
	padding-block: 0 8px;
	padding-inline: 12px 8px;
}
</style>
