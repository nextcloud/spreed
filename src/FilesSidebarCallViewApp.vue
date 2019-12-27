<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez <danxuliu@gmail.com>
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
	<p v-show="isInCall">
		Call in progress
	</p>
</template>

<script>
import { PARTICIPANT } from './constants'

export default {

	name: 'FilesSidebarCallViewApp',

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		isInCall() {
			const participantIndex = this.$store.getters.getParticipantIndex(this.token, this.$store.getters.getParticipantIdentifier())
			if (participantIndex === -1) {
				return false
			}

			const participant = this.$store.getters.getParticipant(this.token, participantIndex)

			return participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		},
	},

	methods: {
		setFileInfo(fileInfo) {
		},
	},
}
</script>
