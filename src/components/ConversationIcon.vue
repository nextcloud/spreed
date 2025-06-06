<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="conversation-icon"
		:style="{ '--icon-size': `${size}px` }"
		:class="[themeClass, { offline: offline }]">
		<template v-if="!isOneToOne">
			<div v-if="iconClass"
				class="avatar icon"
				:class="iconClass" />
			<!-- img is used here instead of NcAvatar to explicitly set key required to avoid glitching in virtual scrolling  -->
			<img v-else
				:key="avatarUrl"
				:src="avatarUrl"
				:width="size"
				:height="size"
				:alt="item.displayName"
				class="avatar icon"
				@error="onError">
			<span v-if="!hideUserStatus && conversationType"
				class="conversation-icon__type"
				role="img"
				aria-hidden="false"
				:aria-label="conversationType.label">
				<component :is="conversationType.icon" :size="12" />
			</span>
		</template>
		<!-- NcAvatar doesn't fully support props update and works only for 1 user -->
		<!-- Using key on NcAvatar forces NcAvatar re-mount and solve the problem, could not really optimal -->
		<!-- TODO: Check if props update support in NcAvatar is more performant -->
		<NcAvatar v-else
			:key="item.token + (isDarkTheme ? '-dark' : '-light')"
			:size="size"
			:user="item.name"
			:disable-menu="disableMenu"
			:display-name="item.displayName"
			:preloaded-user-status="preloadedUserStatus"
			:hide-status="hideUserStatus"
			:verbose-status="showUserOnlineStatus"
			class="conversation-icon__avatar" />
		<div v-if="showCall" class="overlap-icon">
			<IconVideo :size="20" fill-color="#E9322D" />
			<span class="hidden-visually">{{ t('spreed', 'Call in progress') }}</span>
		</div>
		<div v-else-if="showFavorite" class="overlap-icon">
			<IconStar :size="20" fill-color="#FFCC00" />
			<span class="hidden-visually">{{ t('spreed', 'Favorite') }}</span>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import { ref } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import IconLink from 'vue-material-design-icons/Link.vue'
import IconStar from 'vue-material-design-icons/Star.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'
import IconWeb from 'vue-material-design-icons/Web.vue'
import { AVATAR, CONVERSATION } from '../constants.ts'
import { getConversationAvatarOcsUrl } from '../services/avatarService.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { getFallbackIconClass } from '../utils/conversation.ts'
import { getPreloadedUserStatus } from '../utils/userStatus.ts'

const supportsAvatar = hasTalkFeature('local', 'avatar')

export default {
	name: 'ConversationIcon',

	components: {
		IconStar,
		IconVideo,
		NcAvatar,
	},

	props: {
		/**
		 * Allow to hide the favorite icon, e.g. on mentions
		 */
		hideFavorite: {
			type: Boolean,
			default: true,
		},

		hideCall: {
			type: Boolean,
			default: true,
		},

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
					isFavorite: false,
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
		showCall() {
			return !this.hideCall && this.item.hasCall
		},

		showFavorite() {
			return !this.hideFavorite && this.item.isFavorite
		},

		preloadedUserStatus() {
			if (this.hideUserStatus) {
				return undefined
			}

			return getPreloadedUserStatus(this.item)
		},

		iconClass() {
			return getFallbackIconClass(this.item, this.failed)
		},

		themeClass() {
			return `conversation-icon--${this.isDarkTheme ? 'dark' : 'bright'}`
		},

		isOneToOne() {
			return this.item.type === CONVERSATION.TYPE.ONE_TO_ONE
		},

		conversationType() {
			if (this.item.remoteServer) {
				return { key: 'federated', icon: IconWeb, label: t('spreed', 'Federated conversation') }
			} else if (this.item.type === CONVERSATION.TYPE.PUBLIC) {
				return { key: 'public', icon: IconLink, label: t('spreed', 'Public conversation') }
			}
			return null
		},

		avatarUrl() {
			if (!supportsAvatar || this.item.isDummyConversation) {
				return undefined
			}

			return getConversationAvatarOcsUrl(this.item.token, this.isDarkTheme, this.item.avatarVersion)
		},
	},

	methods: {
		t,
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

	&__type {
		position: absolute;
		inset-inline-end: -2px;
		bottom: -2px;
		display: flex;
		align-content: center;
		justify-content: center;
		height: clamp(14px, 40%, 18px);
		width: clamp(14px, 40%, 18px);
		border: 1px solid var(--color-main-background);
		background-color: var(--color-main-background);
		color: var(--color-main-text);
		border-radius: 50%;
	}

	.overlap-icon {
		position: absolute;
		top: 0;
		inset-inline-start: calc(var(--icon-size) - 12px);
		line-height: 100%;
		display: inline-block;
		vertical-align: middle;
	}
}

.offline {
	opacity: .4;
}

</style>
