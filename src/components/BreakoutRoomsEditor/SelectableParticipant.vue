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
		<!--
			Previously it was $emit('', event.target.value), where
			event.target.value - is just a string from value attr
			So "update" event changes Array value with String value

			Native checkbox works only with boolean: checked / not checked.
			Array for checkbox - is a feature of checkbox's v-model in Vue
			Solution - use v-model.
			Bad way: v-model="checked", but it is direct prop mutation.
			Better solution: proxy v-model with computed with custom setter
		-->
		<!--  -->
		<!--   -->
		<!-- -->
		<input id="participant.attendeeId"
			v-model="modelProxy"
			:value="participant.attendeeId"
			type="checkbox"
			name="participant.attendeeId">
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

	// Best practice + migration to Vue 3
	emits: ['update:checked'],

	computed: {
		modelProxy: {
			get() {
				// Value for v-model is just "checked"
				// The same as v-model="checked"
				return this.checked
			},
			set(value) {
				// But instead of mutating checked in v-model="checked" we should emit event with the value was set
				this.$emit('update:checked', value)
			},
		},
	},
}
</script>

<style lang="scss" scoped>

</style>
