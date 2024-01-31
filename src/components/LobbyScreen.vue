<!--
  - @copyright Copyright (c) 2020, Daniel Calviño Sánchez <danxuliu@gmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
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

			<p v-if="countdown"
				class="lobby__countdown">
				{{ message }} -
				<span class="lobby__countdown live-relative-timestamp"
					:data-timestamp="countdown * 1000"
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
		<SetGuestUsername v-if="currentUserIsGuest" />
	</div>
</template>

<script>
import RoomService from 'vue-material-design-icons/RoomService.vue'

import moment from '@nextcloud/moment'

import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'

import GuestWelcomeWindow from './GuestWelcomeWindow.vue'
import SetGuestUsername from './SetGuestUsername.vue'

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

		countdown() {
			return this.conversation.lobbyTimer
		},

		relativeDate() {
			const diff = moment().diff(this.timerInMoment)
			if (diff > -45000 && diff < 45000) {
				return t('spreed', 'The meeting will start soon')
			}
			return this.timerInMoment.fromNow()
		},

		timerInMoment() {
			return moment.unix(this.countdown)
		},

		startTime() {
			return this.timerInMoment.format('LLL')
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

</style>
