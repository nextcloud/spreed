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
		:class="{'offline': offline}">
		<div v-if="iconClass"
			class="avatar icon"
			:class="iconClass" />
		<NcAvatar v-else
			:size="44"
			:user="item.name"
			:disable-menu="disableMenu"
			:display-name="item.displayName"
			:preloaded-user-status="preloadedUserStatus"
			:show-user-status-compact="disableMenu"
			:menu-container="menuContainer"
			menu-position="left"
			class="conversation-icon__avatar" />
		<div v-if="showCall"
			class="overlap-icon">
			<VideoIcon :size="20"
				:fill-color="'#E9322D'" />
			<span class="hidden-visually">{{ t('spreed', 'Call in progress') }}</span>
		</div>
		<div v-else-if="showFavorite"
			class="overlap-icon">
			<Star :size="20"
				:fill-color="'#FFCC00'" />
			<span class="hidden-visually">{{ t('spreed', 'Favorite') }}</span>
		</div>
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import Star from 'vue-material-design-icons/Star.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import { CONVERSATION } from '../constants.js'

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
	},

	computed: {
		showCall() {
			return !this.hideCall && this.item.hasCall
		},
		showFavorite() {
			return !this.hideFavorite && this.item.isFavorite
		},
		iconClass() {
			if (this.item.objectType === 'file') {
				return 'icon-file'
			} else if (this.item.objectType === 'share:password') {
				return 'icon-password'
			} else if (this.item.objectType === 'emails') {
				return 'icon-mail'
			} else if (this.item.type === CONVERSATION.TYPE.CHANGELOG) {
				return 'icon-changelog'
			} else if (this.item.type === CONVERSATION.TYPE.GROUP) {
				return 'icon-contacts'
			} else if (this.item.type === CONVERSATION.TYPE.PUBLIC) {
				return 'icon-public'
			}

			return ''
		},
		preloadedUserStatus() {
			if (Object.prototype.hasOwnProperty.call(this.item, 'statusMessage')) {
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
			if (!this.$store) {
				return undefined
			}

			return this.$store.getters.getMainContainerSelector()
		},
	},
}
</script>

<style lang="scss" scoped>
$icon-size: 44px;

.conversation-icon {
	width: $icon-size;
	height: $icon-size;
	position: relative;

	.avatar.icon {
		width: $icon-size;
		height: $icon-size;
		line-height: $icon-size;
		font-size: calc($icon-size / 2);
		background-color: var(--color-background-darker);

		&.icon-changelog {
			background-size: $icon-size;
		}

		&.icon-public,
		&.icon-contacts,
		&.icon-password,
		&.icon-file,
		&.icon-mail {
			background-size: calc($icon-size / 2);
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
