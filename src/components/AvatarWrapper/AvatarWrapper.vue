<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
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
	<div class="avatar-wrapper" :class="avatarClass" :style="{'--condensed-overlap': condensedOverlap}">
		<div v-if="iconClass"
			class="icon"
			:class="[`avatar-${size}px`, iconClass]" />
		<div v-else-if="isGuest || isDeletedUser"
			class="guest"
			:class="`avatar-${size}px`">
			{{ firstLetterOfGuestName }}
		</div>
		<div v-else-if="isBot"
			class="bot"
			:class="`avatar-${size}px`">
			{{ '>_' }}
		</div>
		<NcAvatar v-else
			:key="id"
			:user="id"
			:display-name="name"
			:menu-container="menuContainerWithFallback"
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

import { ATTENDEE } from '../../constants.js'

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
		medium: {
			type: Boolean,
			default: false,
		},
		condensed: {
			type: Boolean,
			default: false,
		},
		condensedOverlap: {
			type: Number,
			default: 2,
		},
		offline: {
			type: Boolean,
			default: false,
		},
		highlighted: {
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
			default: false,
		},
		preloadedUserStatus: {
			type: Object,
			default: undefined,
		},
		menuContainer: {
			type: String,
			default: undefined,
		},
	},
	computed: {
		size() {
			return this.small ? 22 : this.medium ? 32 : 44
		},
		// Determines which icon is displayed
		iconClass() {
			if (!this.source || this.isUser || this.isBot || this.isGuest || this.isDeletedUser) {
				return ''
			}
			if (this.source === 'emails') {
				return 'icon-mail'
			}
			if (this.source === 'bots' && this.id === 'changelog') {
				return 'icon-changelog'
			}
			// source: groups, circles
			return 'icon-contacts'
		},
		avatarClass() {
			return {
				'avatar-wrapper--offline': this.offline,
				'avatar-wrapper--small': this.small,
				'avatar-wrapper--medium': this.medium,
				'avatar-wrapper--condensed': this.condensed,
				'avatar-wrapper--highlighted': this.highlighted,
			}
		},
		isUser() {
			return this.source === 'users' || this.source === ATTENDEE.ACTOR_TYPE.BRIDGED
		},
		isBot() {
			return this.source === 'bots' && this.id !== 'changelog'
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
			const customName = this.name?.trim() && this.name !== t('spreed', 'Guest') ? this.name : '?'
			return customName.charAt(0)
		},
		menuContainerWithFallback() {
			return this.menuContainer ?? this.$store.getters.getMainContainerSelector()
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
@import '../../assets/avatar';

.avatar-wrapper {
	height: 44px;
	width: 44px;
	border-radius: 44px;
	@include avatar-mixin(44px);

	&--small {
		height: 22px;
		width: 22px;
		border-radius: 22px;
		@include avatar-mixin(22px);
	}

	&--medium {
		height: 32px;
		width: 32px;
		border-radius: 32px;
		@include avatar-mixin(32px);
	}

	&--condensed {
		width: unset;
		height: unset;
		margin-left: calc(var(--condensed-overlap) * -1px);
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

	&--highlighted {
		outline: 2px solid var(--color-primary-element);
	}
}

</style>
