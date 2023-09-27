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
	<div class="conversation-icon"
		:style="{'--icon-size': `${size}px`}"
		:class="{'offline': offline}">
		<div v-if="iconClass"
			class="avatar icon"
			:class="iconClass" />
		<!-- img is used here instead of NcAvatar to explicitly set key required to avoid glitching in virtual scrolling  -->
		<img v-else-if="!isOneToOne"
			:key="avatarUrl"
			:src="avatarUrl"
			:width="size"
			:height="size"
			:alt="item.displayName"
			class="avatar icon">
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
			menu-position="left"
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
import Star from 'vue-material-design-icons/Star.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { generateOcsUrl } from '@nextcloud/router'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import { CONVERSATION } from '../constants.js'
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
			default: 44,
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
				return 'icon-contacts'
			}

			if (!supportsAvatar) {
				if (this.item.objectType === 'file') {
					return 'icon-file'
				} else if (this.item.objectType === 'share:password') {
					return 'icon-password'
				} else if (this.item.objectType === 'emails') {
					return 'icon-mail'
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

			if (this.item.type === CONVERSATION.TYPE.GROUP) {
				// Group icon for group conversation suggestions
				return 'icon-contacts'
			}

			if (this.item.type === CONVERSATION.TYPE.PUBLIC) {
				// Public icon for new conversation dialog
				return 'icon-public'
			}

			// Fall-through for other conversation suggestions to user-avatar handling
			return undefined
		},

		isOneToOne() {
			return this.item.type === CONVERSATION.TYPE.ONE_TO_ONE
		},

		avatarUrl() {
			if (!supportsAvatar) {
				return undefined
			}

			const avatarEndpoint = 'apps/spreed/api/v1/room/{token}/avatar' + (isDarkTheme ? '/dark' : '')

			return generateOcsUrl(avatarEndpoint + '?v={avatarVersion}', {
				token: this.item.token,
				avatarVersion: this.item.avatarVersion,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
$icon-size: var(--icon-size, 44px);

.conversation-icon {
	width: $icon-size;
	height: $icon-size;
	position: relative;

	.avatar.icon {
		display: block;
		width: $icon-size;
		height: $icon-size;
		line-height: $icon-size;
		background-size: calc($icon-size / 2);
		background-color: var(--color-background-darker);

		&.icon-changelog {
			background-size: cover !important;
		}
	}

	.overlap-icon {
		position: absolute;
		top: 0;
		left: calc(#{$icon-size} - 12px);
		line-height: 100%;
		display: inline-block;
		vertical-align: middle;
	}
}

.offline {
	opacity: .4;
}

</style>
