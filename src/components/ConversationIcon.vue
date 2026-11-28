<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="conversation-icon"
		:style="{ '--icon-size': `${size}px` }"
		:class="[themeClass, { offline: offline }]">
		<template v-if="!isOneToOne">
			<div
				v-if="iconClass"
				class="avatar icon"
				:class="iconClass" />
			<!-- img is used here instead of NcAvatar to explicitly set key required to avoid glitching in virtual scrolling  -->
			<img
				v-else
				:key="avatarUrl"
				:src="avatarUrl"
				:width="size"
				:height="size"
				:alt="item.displayName"
				class="avatar icon"
				@error="onError">
		</template>
		<!-- NcAvatar doesn't fully support props update and works only for 1 user -->
		<!-- Using key on NcAvatar forces NcAvatar re-mount and solve the problem, could not really optimal -->
		<!-- TODO: Check if props update support in NcAvatar is more performant -->
		<NcAvatar
			v-else
			:key="item.token + (isDarkTheme ? '-dark' : '-light')"
			:size
			:user="item.name"
			:disableMenu
			:displayName="item.displayName"
			:preloadedUserStatus
			:hideStatus="hideUserStatus"
			:verboseStatus="showUserOnlineStatus"
			class="conversation-icon__avatar" />
	</div>
</template>

<script>
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import { ref } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import { AVATAR, CONVERSATION } from '../constants.ts'
import { getConversationAvatarOcsUrl } from '../services/avatarService.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { getFallbackIconClass } from '../utils/conversation.ts'
import { getPreloadedUserStatus } from '../utils/userStatus.ts'

const supportsAvatar = hasTalkFeature('local', 'avatar')

export default {
	name: 'ConversationIcon',

	components: {
		NcAvatar,
	},

	props: {
		disableMenu: {
			type: Boolean,
			default: true,
		},

		hideUserStatus: {
			type: Boolean,
			default: false,
		},

		showUserOnlineStatus: {
			type: Boolean,
			default: false,
		},

		item: {
			type: Object,
			default() {
				return {
					objectType: '',
					type: 0,
					displayName: '',
				}
			},
		},

		/**
		 * Reduces the opacity of the icon if true
		 */
		offline: {
			type: Boolean,
			default: false,
		},

		size: {
			type: Number,
			default: AVATAR.SIZE.DEFAULT,
		},
	},

	setup() {
		const isDarkTheme = useIsDarkTheme()

		const failed = ref(false)

		/**
		 * If avatar image failed to load, toggle value to provide a fallback
		 */
		function onError() {
			failed.value = true
		}

		return {
			isDarkTheme,
			failed,
			onError,
		}
	},

	computed: {
		preloadedUserStatus() {
			if (this.hideUserStatus) {
				return undefined
			}

			return getPreloadedUserStatus(this.item)
		},

		canRequestAvatar() {
			if (!supportsAvatar || this.item.isDummyConversation) {
				return false
			}

			// Endpoint limited with #RequireParticipantOrLoggedInAndListedConversation
			return this.item.attendeeId || this.item.listable !== CONVERSATION.LISTABLE.NONE
		},

		iconClass() {
			return getFallbackIconClass(this.item, this.failed || !this.canRequestAvatar)
		},

		themeClass() {
			return `conversation-icon--${this.isDarkTheme ? 'dark' : 'bright'}`
		},

		isOneToOne() {
			return this.item.type === CONVERSATION.TYPE.ONE_TO_ONE
		},

		avatarUrl() {
			if (!this.canRequestAvatar) {
				return undefined
			}

			return getConversationAvatarOcsUrl(this.item.token, this.isDarkTheme, this.item.avatarVersion)
		},
	},
}
</script>

<style lang="scss" scoped>
.conversation-icon {
	width: var(--icon-size);
	height: var(--icon-size);
	position: relative;

	.avatar.icon {
		display: block;
		width: var(--icon-size);
		height: var(--icon-size);
		line-height: var(--icon-size);
		background-size: calc(var(--icon-size) / 2);
		background-color: var(--color-text-maxcontrast-default);

		&.icon-changelog {
			background-size: cover !important;
		}
	}

	img.avatar.icon {
		background-color: transparent;
	}

	&--dark .avatar.icon {
		background-color: #3B3B3B;
	}

}

.offline {
	opacity: .4;
}

.conversation-icon :deep(.icon-vue) {
	min-width: 0 !important;
	min-height: 0 !important;
}

</style>
