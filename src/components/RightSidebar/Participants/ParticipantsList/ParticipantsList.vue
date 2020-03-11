<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<div>
		<ul>
			<Participant
				v-for="item in items"
				:key="item.id"
				:participant="item"
				@clickParticipant="handleClickParticipant" />
		</ul>
	</div>
</template>

<script>

import Participant from './Participant/Participant'

export default {
	name: 'ParticipantsList',

	components: {
		Participant,
	},

	props: {
		/**
		 * List of searched users or groups
		 */
		items: {
			type: Array,
			required: true,
		},
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		dummyParticipants() {
			const dummies = 6 - this.items.length
			return dummies > 0 ? dummies : 0
		},
	},

	methods: {
		async handleClickParticipant(participant) {
			this.$emit('click', participant)
		},
	},
}
</script>

<style lang="scss" scoped>
</style>
