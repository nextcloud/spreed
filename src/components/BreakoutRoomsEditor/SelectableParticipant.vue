<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<label class="selectable-participant">
		<input v-model="modelProxy"
			:value="participant.attendeeId"
			type="checkbox"
			class="selectable-participant__checkbox">
		<!-- Participant's avatar -->
		<AvatarWrapper :id="participant.actorId"
			:name="participant.displayName"
			:source="participant.source || participant.actorType"
			disable-menu
			disable-tooltip
			:preloaded-user-status="preloadedUserStatus"
			show-user-status />

		<span class="selectable-participant__content">
			<span class="selectable-participant__content-name">
				{{ participant.displayName }}
			</span>
			<span v-if="participantStatus"
				class="selectable-participant__content-subname">
				{{ participantStatus }}
			</span>
		</span>

		<IconCheck class="selectable-participant__check-icon" :size="20" />
	</label>
</template>

<script>
import IconCheck from 'vue-material-design-icons/Check.vue'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { getPreloadedUserStatus, getStatusMessage } from '../../utils/userStatus.js'

export default {
	name: 'SelectableParticipant',

	components: {
		AvatarWrapper,
		IconCheck,
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

		preloadedUserStatus() {
			return getPreloadedUserStatus(this.participant)
		},

		participantStatus() {
			return getStatusMessage(this.participant)
		},
	},
}
</script>

<style lang="scss" scoped>

.selectable-participant {
	position: relative;
	display: flex;
	align-items: center;
	gap: calc(2 * var(--default-grid-baseline));
	padding: var(--default-grid-baseline);
	margin: var(--default-grid-baseline);
	border-radius: var(--border-radius-element, 32px);
	line-height: 20px;

	&:hover,
	&:focus-within,
	&:has(:active),
	&:has(:focus-visible) {
		background-color: var(--color-background-hover);
	}

	&:has(input:focus-visible) {
		outline: 2px solid var(--color-main-text);
		box-shadow: 0 0 0 4px var(--color-main-background);
	}

	&:has(input:checked) {
		background-color: var(--color-primary-light);

		&:hover,
		&:focus-within,
		&:has(:focus-visible),
		&:has(:active) {
			background-color: var(--color-primary-light-hover);
		}
	}

	&:has(input:checked) &__check-icon {
		display: flex;
	}

	&__checkbox {
		position: absolute;
		top: 0;
		left: 0;
		z-index: -1;
	}

	&__content {
		display: flex;
		flex-direction: column;
		align-items: flex-start;

		&-name {
			font-weight: 500;
		}
		&-subname {
			font-weight: 400;
			color: var(--color-text-maxcontrast);
		}
	}

	&__check-icon {
		display: none;
		margin-left: auto;
		width: var(--default-clickable-area);
	}
}

</style>
