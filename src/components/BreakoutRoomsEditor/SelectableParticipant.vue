<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
