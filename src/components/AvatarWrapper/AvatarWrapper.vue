<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="avatar-wrapper"
		:class="{
			'avatar-wrapper--offline': offline,
			'avatar-wrapper--small': small,
			'avatar-wrapper--condensed': condensed,
		}">
		<div v-if="iconClass"
			class="icon"
			:class="[`avatar-${size}px`, iconClass]" />
		<div v-else-if="isGuest || isDeletedUser"
			class="guest"
			:class="`avatar-${size}px`">
			{{ firstLetterOfGuestName }}
		</div>
		<NcAvatar v-else
			:user="id"
			:display-name="name"
			:menu-container="menuContainer"
			menu-position="left"
			:disable-tooltip="disableTooltip"
			:disable-menu="isDisabledMenu"
			:show-user-status="showUserStatus"
			:show-user-status-compact="showUserStatusCompact"
			:preloaded-user-status="preloadedUserStatus"
			:size="size" />
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

export default {

	name: 'AvatarWrapper',

	components: {
		NcAvatar,
	},

	props: {
		name: {
			type: String,
			required: true,
		},
		id: {
			type: String,
			default: null,
		},
		source: {
			type: String,
			default: null,
		},
		small: {
			type: Boolean,
			default: false,
		},
		condensed: {
			type: Boolean,
			default: false,
		},
		offline: {
			type: Boolean,
			default: false,
		},
		disableTooltip: {
			type: Boolean,
			default: false,
		},
		disableMenu: {
			type: Boolean,
			default: false,
		},
		showUserStatus: {
			type: Boolean,
			default: false,
		},
		showUserStatusCompact: {
			type: Boolean,
			default: true,
		},
		preloadedUserStatus: {
			type: Object,
			default: undefined,
		},
		container: {
			type: String,
			default: undefined,
		},
	},
	computed: {
		size() {
			return this.small ? 22 : 44
		},
		// Determines which icon is displayed
		iconClass() {
			if (!this.source || this.source === 'users' || this.isGuest || this.isDeletedUser) {
				return ''
			}
			if (this.source === 'emails') {
				return 'icon-mail'
			}
			// source: groups, circles
			return 'icon-contacts'
		},
		isGuest() {
			return this.source === 'guests'
		},
		isDeletedUser() {
			return this.source === 'deleted_users'
		},
		firstLetterOfGuestName() {
			if (this.isDeletedUser) {
				return 'X'
			}
			const customName = this.name !== t('spreed', 'Guest') ? this.name : '?'
			return customName.charAt(0)
		},
		menuContainer() {
			return this.container ?? this.$store.getters.getMainContainerSelector()
		},
		isDisabledMenu() {
			// NcAvatarMenu doesn't work on Desktop
			// See: https://github.com/nextcloud/talk-desktop/issues/34
			return IS_DESKTOP || this.disableMenu
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/avatar.scss';

.avatar-wrapper {
	height: 44px;
	width: 44px;
	@include avatar-mixin(44px);

	&--small {
		height: 22px;
		width: 22px;
		@include avatar-mixin(22px);
	}

	&--condensed {
		width: unset;
		height: unset;
		margin-left: -2px;
		display: flex;

		& > .icon,
		& > .guest,
		:deep(img) {
			outline: 2px solid var(--color-main-background);
		}
	}

	&--offline {
		opacity: .4;

		& :deep(.avatardiv) {
			background: rgba(var(--color-main-background-rgb), .4) !important;
		}
	}
}

</style>
