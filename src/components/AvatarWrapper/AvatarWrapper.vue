<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="avatar-wrapper" :class="avatarClass" :style="avatarStyle">
		<div v-if="iconClass" class="avatar icon" :class="[iconClass]" />
		<div v-else-if="isGuestUser" class="avatar guest">
			{{ firstLetterOfGuestName }}
		</div>
		<div v-else-if="isBot" class="avatar bot">
			>_
		</div>
		<img v-else-if="isFederatedUser && token"
			:key="avatarUrl"
			:src="avatarUrl"
			:width="size"
			:height="size"
			:alt="name"
			class="avatar icon"
			@error="failed = true">
		<NcAvatar v-else
			:key="id + (isDarkTheme ? '-dark' : '-light')"
			:user="id"
			:display-name="name"
			:menu-container="menuContainer"
			:disable-tooltip="disableTooltip"
			:disable-menu="disableMenu"
			:hide-status="!showUserStatus"
			:verbose-status="!showUserStatusCompact"
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
		<NcLoadingIcon v-if="loading"
			:size="size / 2"
			class="loading-avatar" />
	</div>
</template>

<script>
import { ref } from 'vue'

import WebIcon from 'vue-material-design-icons/Web.vue'

import { t } from '@nextcloud/l10n'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'

import { ATTENDEE, AVATAR } from '../../constants.ts'
import { getUserProxyAvatarOcsUrl } from '../../services/avatarService.ts'

export default {

	name: 'AvatarWrapper',

	components: {
		NcAvatar,
		WebIcon,
		NcLoadingIcon,
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

		loading: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		const isDarkTheme = useIsDarkTheme()

		const failed = ref(false)

		return {
			isDarkTheme,
			failed,
		}
	},

	computed: {
		// Determines which icon is displayed
		iconClass() {
			if (!this.source) {
				return ''
			}
			switch (this.source) {
				case ATTENDEE.ACTOR_TYPE.USERS:
				case ATTENDEE.ACTOR_TYPE.BRIDGED:
					return !this.failed ? '' : 'icon-user'
				case ATTENDEE.ACTOR_TYPE.FEDERATED_USERS:
					return (this.token && !this.failed) ? '' : 'icon-user'
				case ATTENDEE.ACTOR_TYPE.EMAILS:
					return this.token === 'new' ? 'icon-mail' : (this.hasCustomName ? '' : 'icon-user')
				case ATTENDEE.ACTOR_TYPE.GUESTS:
					return this.hasCustomName ? '' : 'icon-user'
				case ATTENDEE.ACTOR_TYPE.DELETED_USERS:
					return 'icon-user'
				case ATTENDEE.ACTOR_TYPE.PHONES:
					return 'icon-phone'
				case ATTENDEE.ACTOR_TYPE.BOTS:
					return [ATTENDEE.CHANGELOG_BOT_ID, ATTENDEE.SAMPLE_BOT_ID].includes(this.id) ? 'icon-changelog' : ''
				case ATTENDEE.ACTOR_TYPE.CIRCLES:
					return 'icon-team'
				case ATTENDEE.ACTOR_TYPE.GROUPS:
				default:
					return 'icon-contacts'
			}
		},

		avatarClass() {
			return {
				'avatar-wrapper--dark': this.isDarkTheme,
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

		isFederatedUser() {
			return this.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
		},

		isBot() {
			return this.source === ATTENDEE.ACTOR_TYPE.BOTS && this.id !== ATTENDEE.CHANGELOG_BOT_ID && this.id !== ATTENDEE.SAMPLE_BOT_ID
		},

		isGuestUser() {
			return [ATTENDEE.ACTOR_TYPE.GUESTS, ATTENDEE.ACTOR_TYPE.EMAILS].includes(this.source)
		},

		hasCustomName() {
			return this.name?.trim() && this.name !== t('spreed', 'Guest')
		},

		firstLetterOfGuestName() {
			return this.name?.trim()?.toUpperCase()?.charAt(0) ?? '?'
		},

		avatarUrl() {
			return getUserProxyAvatarOcsUrl(this.token, this.id, this.isDarkTheme, this.size > AVATAR.SIZE.MEDIUM ? 512 : 64)
		},
	},

	watch: {
		avatarUrl() {
			this.failed = false
		},
	},

	methods: {
		t,
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
		display: block;
		width: var(--avatar-size);
		height: var(--avatar-size);
		max-height: var(--avatar-size);
		max-width: var(--avatar-size);
		line-height: var(--avatar-size);
		font-size: calc(var(--avatar-size) / 2);
		overflow: hidden;
		border-radius: 50%;
		background-color: var(--color-text-maxcontrast-default);

		&.icon {
			background-size: calc(var(--avatar-size) / 2);
			&.icon-changelog {
				background-size: cover !important;
			}
		}

		&.bot {
			padding-inline-start: 5px;
			background-color: var(--color-background-darker);
		}

		&.guest {
			color: #ffffff;
			padding: 0;
			display: block;
			text-align: center;
			margin-inline: auto;
		}
	}

	&--condensed {
		width: unset;
		height: unset;
		margin-inline-start: calc(var(--condensed-overlap) * -1px);
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
		inset-inline-end: -4px;
		bottom: -4px;
		height: 18px;
		width: 18px;
		border: 2px solid var(--color-main-background);
		background-color: var(--color-main-background);
		border-radius: 50%;
	}
}

.loading-avatar {
	position: absolute;
	top: 0;
	inset-inline-start: 0;
	width: 100%;
	height: 100%;
}

</style>
