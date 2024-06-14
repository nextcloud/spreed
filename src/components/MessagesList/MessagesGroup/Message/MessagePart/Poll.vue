<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<!-- Poll card -->
	<a v-if="!showAsButton"
		v-observe-visibility="getPollData"
		:aria-label="t('spreed', 'Poll')"
		class="poll-card"
		role="button"
		@click="openPoll">
		<span class="poll-card__header">
			<PollIcon :size="20" />
			<span>{{ name }}</span>
		</span>
		<span class="poll-card__footer">
			{{ pollFooterText }}
		</span>
	</a>

	<!-- Poll results button in system message -->
	<NcButton v-else
		class="poll-closed"
		type="secondary"
		@click="openPoll">
		{{ t('spreed', 'See results') }}
	</NcButton>
</template>

<script>
import PollIcon from 'vue-material-design-icons/Poll.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { POLL } from '../../../../../constants.js'

export default {
	name: 'Poll',

	components: {
		NcButton,
		PollIcon,
	},

	props: {
		name: {
			type: String,
			required: true,
		},

		id: {
			type: String,
			required: true,
		},

		token: {
			type: String,
			required: true,
		},

		showAsButton: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		poll() {
			return this.$store.getters.getPoll(this.token, this.id)
		},

		pollFooterText() {
			if (this.poll?.status === POLL.STATUS.OPEN) {
				return this.poll?.votedSelf.length > 0
					? t('spreed', 'Open poll • You voted already')
					: t('spreed', 'Open poll • Click to vote')
			} else {
				return this.poll?.status === POLL.STATUS.CLOSED
					? t('spreed', 'Poll • Ended')
					: t('spreed', 'Poll')
			}
		},
	},

	methods: {
		t,
		getPollData() {
			if (!this.poll) {
				this.$store.dispatch('getPollData', {
					token: this.token,
					pollId: this.id,
				})
			}
		},

		openPoll() {
			this.$store.dispatch('setActivePoll', {
				token: this.token,
				pollId: this.id,
				name: this.name,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.poll-card {
	display: block;
	max-width: 300px;
	padding: 16px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	transition: border-color 0.1s ease-in-out;

	&:hover,
	&:focus,
	&:focus-visible {
		border-color: var(--color-primary-element);
		outline: none;
	}

	&__header {
		display: flex;
		align-items: flex-start;
		gap: 8px;
		margin-bottom: 16px;
		font-weight: bold;
		white-space: normal;
		word-wrap: anywhere;

		:deep(.material-design-icon) {
			margin-bottom: auto;
		}
	}

	&__footer {
		color: var(--color-text-maxcontrast);
		white-space: normal;
	}
}

.poll-closed {
	margin: 4px auto;
}
</style>
