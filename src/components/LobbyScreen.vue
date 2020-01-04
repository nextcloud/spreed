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
	<div class="lobby emptycontent">
		<div class="icon icon-lobby" />
		<h2>{{ currentConversationName }}</h2>
		<p>{{ message }}</p>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'

export default {

	name: 'LobbyScreen',

	computed: {

		token() {
			return this.$store.getters.getToken()
		},

		currentConversation() {
			return this.$store.getters.conversations[this.token]
		},

		currentConversationName() {
			return this.currentConversation ? this.currentConversation.displayName : ''
		},

		message() {
			let message = t('spreed', 'You are currently waiting in the lobby')

			if (this.currentConversation.lobbyTimer) {
				// PHP timestamp is second-based; JavaScript timestamp is
				// millisecond based.
				const startTime = moment.unix(this.currentConversation.lobbyTimer).format('LLL')
				message = t('spreed', 'You are currently waiting in the lobby. This meeting is scheduled for {startTime}', { startTime: startTime })
			}

			return message
		},

	},

}
</script>
