<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<label class="selectable-participant" :data-nav-id="participantNavigationId">
		<input v-model="modelProxy"
			:value="value"
			:aria-label="participantAriaLabel"
			type="checkbox"
			class="selectable-participant__checkbox"
			@keydown.enter.stop.prevent="handleEnter">
		<!-- Participant's avatar -->
		<AvatarWrapper :id="actorId"
			token="new"
			:name="computedName"
			:source="actorType"
			disable-menu
			disable-tooltip
			:preloaded-user-status="preloadedUserStatus"
			:show-user-status="showUserStatus" />

		<span class="selectable-participant__content">
			<span class="selectable-participant__content-name">
				{{ computedName }}
			</span>
			<span v-if="participantStatus"
				class="selectable-participant__content-subname">
				{{ participantStatus }}
			</span>
		</span>

		<IconCheck v-if="isBulkSelection" class="selectable-participant__check-icon" :size="20" />
	</label>
</template>

<script>
import { inject } from 'vue'

import IconCheck from 'vue-material-design-icons/Check.vue'

import { t } from '@nextcloud/l10n'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { getPreloadedUserStatus, getStatusMessage } from '../../utils/userStatus.ts'

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

		showUserStatus: {
			type: Boolean,
			default: true,
		},
	},

	emits: ['update:checked', 'click-participant'],

	setup() {
		// Toggles the bulk selection state of this component
		const isBulkSelection = inject('bulkParticipantsSelection', false)

		return {
			isBulkSelection,
		}
	},

	computed: {
		modelProxy: {
			get() {
				return this.checked
			},
			set(value) {
				this.isBulkSelection
					? this.$emit('update:checked', value)
					: this.$emit('click-participant', this.participant)
			},
		},

		value() {
			return this.participant.attendeeId || this.participant
		},

		actorId() {
			return this.participant.actorId || this.participant.id
		},

		actorType() {
			return this.participant.actorType || this.participant.source
		},

		computedName() {
			return this.participant.displayName || this.participant.label
		},

		preloadedUserStatus() {
			return getPreloadedUserStatus(this.participant)
		},

		participantStatus() {
			return this.participant.shareWithDisplayNameUnique
				?? getStatusMessage(this.participant)
		},

		participantAriaLabel() {
			return t('spreed', 'Add participant "{user}"', { user: this.computedName })
		},

		participantNavigationId() {
			if (this.participant.actorType && this.participant.actorId) {
				return this.participant.actorType + '_' + this.participant.actorId
			} else {
				return this.participant.source + '_' + this.participant.id
			}
		},
	},

	methods: {
		t,

		handleEnter(event) {
			if (this.isBulkSelection) {
				event.target.click()
			} else {
				this.$emit('click-participant', this.participant)
			}
		},
	}
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

	&, & * {
		cursor: pointer;
	}

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
		opacity: 0;
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
		flex-shrink: 0;
	}
}

</style>
