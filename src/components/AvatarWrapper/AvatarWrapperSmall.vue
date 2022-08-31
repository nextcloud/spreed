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
			'offline': offline,
			'avatar-wrapper--condensed': condensed,
		}">
		<div v-if="iconClass"
			class="icon"
			:class="[`avatar-${sizeToString}px`, iconClass]" />
		<NcAvatar v-else-if="!isGuest"
			:user="id"
			:display-name="name"
			:menu-container="menuContainer"
			menu-position="left"
			:disable-tooltip="disableTooltip"
			:disable-menu="disableMenu"
			:show-user-status="showUserStatus"
			:size="size" />
		<div v-else
			class="guest"
			:class="`avatar-${sizeToString}px`">
			{{ firstLetterOfGuestName }}
		</div>
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

export default {

	name: 'AvatarWrapperSmall',

	components: {
		NcAvatar,
	},

	props: {
		name: {
			type: String,
			default: null,
		},
		id: {
			type: String,
			default: null,
		},
		source: {
			type: String,
			default: null,
		},
		offline: {
			type: Boolean,
			default: false,
		},
		size: {
			type: Number,
			default: 22,
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
			default: true,
		},

		condensed: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		// Determines which icon is displayed
		iconClass() {
			if (!this.source || this.source === 'users' || this.isGuest) {
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
		firstLetterOfGuestName() {
			const customName = this.name !== t('spreed', 'Guest') ? this.name : '?'
			return customName.charAt(0)
		},
		menuContainer() {
			return this.$store.getters.getMainContainerSelector()
		},
		// Takes the the size prop and makes it a string for the classes
		sizeToString() {
			return this.size.toString()
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/avatar.scss';

.avatar-wrapper {
	$avatar-size: 22px;
	height: $avatar-size;
	width: $avatar-size;
	@include avatar-mixin($avatar-size);

	&--condensed {
		width: unset;
		height: unset;
		margin-left: -2px;
		display: flex;

		& > .icon,
		& > .guest,
		::v-deep img {
			outline: 2px solid var(--color-main-background);
		}
	}
}

.offline {
	opacity: .4;
}

</style>
