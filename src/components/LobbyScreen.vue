<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="lobby">
		<GuestWelcomeWindow v-if="isGuestWithoutDisplayName" :token="token" />
		<div class="lobby emptycontent">
			<RoomService :size="64" />
			<h2>{{ currentConversationName }}</h2>

			<p class="lobby__timer">
				{{ t('spreed', 'You are currently waiting in the lobby') }}
			</p>

			<p v-if="lobbyTimer"
				class="lobby__countdown">
				{{ message }} -
				<span class="lobby__countdown relative-timestamp"
					:title="startTime">
					{{ relativeDate }}
				</span>
			</p>

			<div class="lobby__description">
				<NcRichText :text="conversation.description"
					dir="auto"
					autolink
					use-extended-markdown />
			</div>
		</div>
		<SetGuestUsername v-if="currentUserIsGuest" class="guest-info" />
	</div>
</template>

<script>
import RoomService from 'vue-material-design-icons/RoomService.vue'

import { t } from '@nextcloud/l10n'

import NcRichText from '@nextcloud/vue/components/NcRichText'

import GuestWelcomeWindow from './GuestWelcomeWindow.vue'
import SetGuestUsername from './SetGuestUsername.vue'

import { formatDateTime, futureRelativeTime } from '../utils/formattedTime.ts'

export default {

	name: 'LobbyScreen',

	components: {
		GuestWelcomeWindow,
		NcRichText,
		RoomService,
		SetGuestUsername,
	},

	computed: {

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		currentConversationName() {
			return this.conversation ? this.conversation.displayName : ''
		},

		lobbyTimer() {
			return this.conversation.lobbyTimer * 1000
		},

		relativeDate() {
			if (Math.abs(Date.now() - this.lobbyTimer) < 45000) {
				return t('spreed', 'The meeting will start soon')
			}
			return futureRelativeTime(this.lobbyTimer)
		},

		startTime() {
			return formatDateTime(this.lobbyTimer, 'LLL')
		},

		message() {
			return t('spreed', 'This meeting is scheduled for {startTime}', { startTime: this.startTime })
		},

		// Determines whether the current user is a guest user
		currentUserIsGuest() {
			return !this.$store.getters.getUserId()
		},

		isGuestWithoutDisplayName() {
			const userName = this.$store.getters.getDisplayName()
			return !userName && this.currentUserIsGuest
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/variables';
@import '../assets/markdown';

.lobby {
	display: flex;
	flex-direction: column;

	&__timer {
		max-width: $messages-list-max-width;
		margin: 0 auto;
	}

	&__countdown,
	&__description {
		max-width: $messages-list-max-width;
		margin: 0 auto;
		margin-top: 25px;
	}

	:deep(.rich-text--wrapper) {
		text-align: start;
		@include markdown;
	}
}

.guest-info {
	display: flex;
	flex-direction: column;
	align-items: center;
}

</style>
