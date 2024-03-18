<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<div class="conversation-icon"
		:style="{'--icon-size': `${size}px`}"
		:class="[themeClass, {'offline': offline}]">
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
				class="avatar icon">
			<span v-if="!hideUserStatus && conversationType"
				class="conversation-icon__type"
				role="img"
				aria-hidden="false"
				:aria-label="conversationType.label">
				<component :is="conversationType.icon" :size="14" />
			</span>
		</template>
		<!-- NcAvatar doesn't fully support props update and works only for 1 user -->
		<!-- Using key on NcAvatar forces NcAvatar re-mount and solve the problem, could not really optimal -->
		<!-- TODO: Check if props update support in NcAvatar is more performant -->
		<NcAvatar v-else
			:key="item.token"
			:size="size"
			:user="item.name"
			:disable-menu="disableMenu"
			:display-name="item.displayName"
			:preloaded-user-status="preloadedUserStatus"
			:show-user-status="!hideUserStatus"
			:show-user-status-compact="!showUserOnlineStatus"
			:menu-container="menuContainer"
			class="conversation-icon__avatar" />
		<div v-if="showCall" class="overlap-icon">
			<VideoIcon :size="20" :fill-color="'#E9322D'" />
			<span class="hidden-visually">{{ t('spreed', 'Call in progress') }}</span>
		</div>
		<div v-else-if="showFavorite" class="overlap-icon">
			<Star :size="20" :fill-color="'#FFCC00'" />
			<span class="hidden-visually">{{ t('spreed', 'Favorite') }}</span>
		</div>
	</div>
</template>

<script>
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import Star from 'vue-material-design-icons/Star.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import WebIcon from 'vue-material-design-icons/Web.vue'

import { getCapabilities } from '@nextcloud/capabilities'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import { AVATAR, CONVERSATION } from '../constants.js'
import { getConversationAvatarOcsUrl } from '../services/avatarService.ts'
import { isDarkTheme } from '../utils/isDarkTheme.js'

const supportsAvatar = getCapabilities()?.spreed?.features?.includes('avatar')

export default {
	name: 'ConversationIcon',

	components: {
		NcAvatar,
		Star,
		VideoIcon,
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

	computed: {
		showCall() {
			return !this.hideCall && this.item.hasCall
		},

		showFavorite() {
			return !this.hideFavorite && this.item.isFavorite
		},

		preloadedUserStatus() {
			if (!this.hideUserStatus && Object.prototype.hasOwnProperty.call(this.item, 'statusMessage')) {
				// We preloaded the status
				return {
					status: this.item.status || null,
					message: this.item.statusMessage || null,
					icon: this.item.statusIcon || null,
				}
			}
			return undefined
		},

		menuContainer() {
			// The store may not be defined in the RoomSelector if used from
			// the Collaboration menu outside Talk.
			return this.$store?.getters.getMainContainerSelector()
		},

		iconClass() {
			if (this.item.isDummyConversation) {
				// Prevent a 404 when trying to load an avatar before the conversation data is actually loaded
				// Also used in new conversation / invitation handler dialog
				const isFed = this.item.remoteServer && 'icon-conversation-federation'
				const type = this.item.type === CONVERSATION.TYPE.PUBLIC ? 'icon-conversation-public' : 'icon-conversation-group'
				return `${isFed || type} icon--dummy`
			}

			if (!supportsAvatar) {
				if (this.item.objectType === CONVERSATION.OBJECT_TYPE.FILE) {
					return 'icon-file'
				} else if (this.item.objectType === CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION) {
					return 'icon-password'
				} else if (this.item.objectType === CONVERSATION.OBJECT_TYPE.EMAIL) {
					return 'icon-mail'
				} else if (this.item.objectType === CONVERSATION.OBJECT_TYPE.PHONE) {
					return 'icon-phone'
				} else if (this.item.objectType === CONVERSATION.OBJECT_TYPE.CIRCLES) {
					return 'icon-team'
				} else if (this.item.type === CONVERSATION.TYPE.CHANGELOG) {
					return 'icon-changelog'
				} else if (this.item.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER) {
					return 'icon-user'
				} else if (this.item.type === CONVERSATION.TYPE.GROUP) {
					return 'icon-contacts'
				} else if (this.item.type === CONVERSATION.TYPE.PUBLIC) {
					return 'icon-public'
				}
				return undefined
			}

			if (this.item.token) {
				// Existing conversations use the /avatar endpoint… Always!
				return undefined
			}

			if (this.item.objectType === CONVERSATION.OBJECT_TYPE.CIRCLES) {
				// Team icon for group conversation suggestions
				return 'icon-team'
			}

			if (this.item.type === CONVERSATION.TYPE.GROUP) {
				// Group icon for group conversation suggestions
				return 'icon-contacts'
			}

			// Fall-through for other conversation suggestions to user-avatar handling
			return undefined
		},

		themeClass() {
			return `conversation-icon--${isDarkTheme ? 'dark' : 'bright'}`
		},

		isOneToOne() {
			return this.item.type === CONVERSATION.TYPE.ONE_TO_ONE
		},

		conversationType() {
			if (this.item.remoteServer) {
				return { icon: WebIcon, label: t('spreed', 'Federated conversation') }
			} else if (this.item.type === CONVERSATION.TYPE.PUBLIC) {
				return { icon: LinkVariantIcon, label: t('spreed', 'Public conversation') }
			}
			return null
		},

		avatarUrl() {
			if (!supportsAvatar || this.item.isDummyConversation) {
				return undefined
			}

			return getConversationAvatarOcsUrl(this.item.token, isDarkTheme, this.item.avatarVersion)
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

		&--dummy {
			background-size: var(--icon-size);
		}

		&.icon-changelog {
			background-size: cover !important;
		}
	}

	&--dark .avatar.icon {
		background-color: #3B3B3B;
	}

	&__type {
		position: absolute;
		right: -4px;
		bottom: -4px;
		height: 18px;
		width: 18px;
		border: 2px solid var(--color-main-background);
		background-color: var(--color-main-background);
		border-radius: 50%;
	}

	.overlap-icon {
		position: absolute;
		top: 0;
		left: calc(var(--icon-size) - 12px);
		line-height: 100%;
		display: inline-block;
		vertical-align: middle;
	}
}

.offline {
	opacity: .4;
}

</style>
