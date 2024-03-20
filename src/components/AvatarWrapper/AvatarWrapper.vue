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
	<div class="avatar-wrapper" :class="avatarClass" :style="avatarStyle">
		<div v-if="iconClass" class="avatar icon" :class="[iconClass]" />
		<div v-else-if="isGuest || isDeletedUser" class="avatar guest">
			{{ firstLetterOfGuestName }}
		</div>
		<div v-else-if="isBot" class="avatar bot">
			{{ '>_' }}
		</div>
		<img v-else-if="isFederatedUser && token"
			:key="avatarUrl"
			:src="avatarUrl"
			:width="size"
			:height="size"
			:alt="name"
			class="avatar icon">
		<NcAvatar v-else
			:key="id"
			:user="id"
			:display-name="name"
			:menu-container="menuContainerWithFallback"
			:disable-tooltip="disableTooltip"
			:disable-menu="disableMenu"
			:show-user-status="showUserStatus"
			:show-user-status-compact="showUserStatusCompact"
			:preloaded-user-status="preloadedUserStatus"
			:size="size" />
		<!-- Override user status for federated users -->
		<span v-if="showUserStatus && isFederatedUser"
			class="avatar-wrapper__user-status"
			role="img"
			aria-hidden="false"
			:aria-label="t('spreed', 'Federated user')">
			<WebIcon :size="14" />
		</span>
	</div>
</template>

<script>
import WebIcon from 'vue-material-design-icons/Web.vue'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import { ATTENDEE, AVATAR } from '../../constants.js'
import { getUserProxyAvatarOcsUrl } from '../../services/avatarService.ts'
import { isDarkTheme } from '../../utils/isDarkTheme.js'

export default {

	name: 'AvatarWrapper',

	components: {
		NcAvatar,
		WebIcon,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
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
		size: {
			type: Number,
			default: AVATAR.SIZE.DEFAULT,
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
		// Determines which icon is displayed
		iconClass() {
			if (!this.source || this.isUser || (this.isFederatedUser && this.token) || this.isBot || this.isGuest || this.isDeletedUser) {
				return ''
			}
			if (this.isFederatedUser) {
				return 'icon-user'
			}
			if (this.source === ATTENDEE.ACTOR_TYPE.EMAILS) {
				return 'icon-mail'
			}
			if (this.source === ATTENDEE.ACTOR_TYPE.PHONES) {
				return 'icon-phone'
			}
			if (this.source === ATTENDEE.ACTOR_TYPE.BOTS && this.id === ATTENDEE.CHANGELOG_BOT_ID) {
				return 'icon-changelog'
			}
			if (this.source === ATTENDEE.ACTOR_TYPE.CIRCLES) {
				return 'icon-team'
			}
			// source: groups
			return 'icon-contacts'
		},
		avatarClass() {
			return {
				'avatar-wrapper--dark': isDarkTheme,
				'avatar-wrapper--offline': this.offline,
				'avatar-wrapper--condensed': this.condensed,
				'avatar-wrapper--highlighted': this.highlighted,
			}
		},
		avatarStyle() {
			return {
				'--avatar-size': this.size + 'px',
				'--condensed-overlap': this.condensedOverlap,
			}
		},
		isUser() {
			return this.source === ATTENDEE.ACTOR_TYPE.USERS || this.source === ATTENDEE.ACTOR_TYPE.BRIDGED
		},
		isFederatedUser() {
			return this.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
		},
		isBot() {
			return this.source === ATTENDEE.ACTOR_TYPE.BOTS && this.id !== ATTENDEE.CHANGELOG_BOT_ID
		},
		isGuest() {
			return this.source === ATTENDEE.ACTOR_TYPE.GUESTS
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
		avatarUrl() {
			return getUserProxyAvatarOcsUrl(this.token, this.id, isDarkTheme, this.size > AVATAR.SIZE.MEDIUM ? 512 : 64)
		},
	},
}
</script>

<style lang="scss" scoped>
.avatar-wrapper {
	position: relative;
	height: var(--avatar-size);
	width: var(--avatar-size);
	border-radius: var(--avatar-size);

	&--dark .avatar {
		background-color: #3B3B3B !important;
	}

	.avatar {
		position: sticky;
		top: 0;
		width: var(--avatar-size);
		height: var(--avatar-size);
		max-height: var(--avatar-size);
		max-width: var(--avatar-size);
		line-height: var(--avatar-size);
		font-size: calc(var(--avatar-size) / 2);
		border-radius: 50%;
		background-color: var(--color-text-maxcontrast-default);

		&.icon {
			background-size: calc(var(--avatar-size) / 2);
			&.icon-changelog {
				background-size: cover !important;
			}
		}

		&.bot {
			padding-left: 5px;
			background-color: var(--color-background-darker);
		}

		&.guest {
			color: #ffffff;
			padding: 0;
			display: block;
			text-align: center;
			margin-left: auto;
			margin-right: auto;
		}
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

	&__user-status {
		position: absolute;
		right: -4px;
		bottom: -4px;
		height: 18px;
		width: 18px;
		border: 2px solid var(--color-main-background);
		background-color: var(--color-main-background);
		border-radius: 50%;
	}
}

</style>
