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
		<div class="lobby emptycontent">
			<div class="icon icon-lobby" />
			<h2>{{ currentConversationName }}</h2>
			<p>{{ message }}</p>
		</div>
		<SetGuestUsername v-if="currentUserIsGuest" />
	</div>
</template>

<script>
import moment from '@nextcloud/moment'
import SetGuestUsername from './SetGuestUsername'

export default {

	name: 'LobbyScreen',

	components: {
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

		message() {
			let message = t('spreed', 'You are currently waiting in the lobby')

			if (this.conversation.lobbyTimer) {
				// PHP timestamp is second-based; JavaScript timestamp is
				// millisecond based.
				const startTime = moment.unix(this.conversation.lobbyTimer).format('LLL')
				message = t('spreed', 'You are currently waiting in the lobby. This meeting is scheduled for {startTime}', { startTime: startTime })
			}

			return message
		},
		// Determines whether the current user is a guest user
		currentUserIsGuest() {
			return !this.$store.getters.getUserId()
		},
	},

}
</script>

<style lang="scss" scoped>

.lobby {
	display: flex;
	flex-direction: column;
}

</style>
