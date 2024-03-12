<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div tabindex="0"
		class="selectable-participant">
		<input id="participant.attendeeId"
			v-model="modelProxy"
			:value="participant.attendeeId"
			type="checkbox"
			name="participant.attendeeId">
		<!-- Participant's avatar -->
		<AvatarWrapper :id="participant.actorId"
			:name="participant.displayName"
			:source="participant.source || participant.actorType"
			disable-menu
			disable-tooltip
			show-user-status
			show-user-status-compact />
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

	emits: ['update:checked'],

	computed: {
		modelProxy: {
			get() {
				return this.checked
			},
			set(value) {
				this.$emit('update:checked', value)
			},
		},
	},
}
</script>

<style lang="scss" scoped>

.selectable-participant {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	margin: var(--default-grid-baseline) 0 var(--default-grid-baseline) 14px;
}

</style>
