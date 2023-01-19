<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div tabindex="0"
		class="participants-editor__participant">
		<input id="participant.attendeeId"
			:value="participant.attendeeId"
			:checked="checked"
			type="checkbox"
			name="participant.attendeeId"
			@change="handleChange">
		<!-- Participant's avatar -->
		<AvatarWrapper :id="participant.id"
			:disable-tooltip="true"
			:disable-menu="true"
			:size="44"
			:show-user-status="true"
			:name="participant.displayName"
			:source="participant.source || participant.actorType" />
		<div>
			{{ participant.displayName }}
		</div>
	</div>
</template>

<script>
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

export default {
	name: 'SelectableParticipant',

	components: {
		AvatarWrapper,
	},

	props: {
		/**
		 * The participant object
		 */
		participant: {
			type: Object,
			required: true,
		},

		checked: {
			type: Array,
			required: true,
		},
	},

	methods: {
		handleChange(event) {
			this.$emit('update:checked', event.target.value)
		},
	},
}
</script>

<style lang="scss" scoped>

</style>
