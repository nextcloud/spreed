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
	<div ref="conversation-icon"
		class="conversation-icon"
		:style="iconStyle"
		:class="{'offline': offline}">
		<div v-if="iconClass"
			class="avatar icon"
			:style="iconStyle"
			:class="iconClass" />
		<NcAvatar v-else-if="hasPicture"
			:url="pictureUrl"
			:size="size" />
		<NcAvatar v-else
			:size="size"
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
import Star from 'vue-material-design-icons/Star.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'

import { generateOcsUrl } from '@nextcloud/router'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

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

		/**
		 * Passing in true will make this component fill all the available space in its container.
		 * This is not reactive as it will take the size of the container once mounted.
		 */
		isBig: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			parentElement: undefined,
		}
	},

	computed: {
		showCall() {
			return !this.hideCall && this.item.hasCall
		},

		showFavorite() {
			return !this.hideFavorite && this.item.isFavorite
		},

		iconClass() {
			if (this.hasPicture) {
				return ''
			}
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

		size() {
			if (!this.isBig || (this.isBig && !this.parentElement)) {
				return 44
			} else {
				return Math.min(this.parentElement.clientHeight, this.parentElement.clientWidth)
			}
		},

		iconStyle() {
			return {
				height: this.size.toString() + 'px',
				width: this.size.toString() + 'px',
			}
		},

		hasPicture() {
			return !!this.item.avatarVersion
		},

		pictureUrl() {
			return generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar?v={avatarVersion}', {
				token: this.item.token,
				avatarVersion: this.item.avatarVersion,
			})
		},
	},

	mounted() {
		// Get the size of the parent once the component is mounted
		this.parentElement = this.$refs?.['conversation-icon']?.parentElement
	},
}
</script>

<style lang="scss" scoped>
$icon-size: 44px;

.conversation-icon {
	position: relative;

	.avatar.icon {
		line-height: $icon-size;
		font-size: calc($icon-size / 2);
		background-color: var(--color-background-darker);

		&.icon-changelog {
			background-size: $icon-size;
		}

		&.icon-public,
		&.icon-contacts,
		&.icon-user,
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
