<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<!-- Poll card -->
	<div v-if="draft" class="poll-card" @click="openDraft">
		<span class="poll-card__header poll-card__header--draft">
			<IconPoll class="poll-card__header-icon" :size="20" />
			<span class="poll-card__header-name">{{ name }}</span>
			<NcButton v-if="canEditPollDraft"
				type="tertiary"
				:title="t('spreed', 'Edit poll draft')"
				:aria-label="t('spreed', 'Edit poll draft')"
				@click.stop="editDraft">
				<template #icon>
					<IconPencil :size="20" />
				</template>
			</NcButton>
			<NcButton type="tertiary"
				:title="t('spreed', 'Delete poll draft')"
				:aria-label="t('spreed', 'Delete poll draft')"
				@click.stop="deleteDraft">
				<template #icon>
					<IconDelete :size="20" />
				</template>
			</NcButton>
		</span>
		<span class="poll-card__footer">
			{{ pollFooterText }}
		</span>
	</div>
	<a v-else-if="!showAsButton"
		v-intersection-observer="getPollData"
		:aria-label="t('spreed', 'Poll')"
		class="poll-card"
		role="button"
		@click="openPoll">
		<span class="poll-card__header">
			<IconPoll class="poll-card__header-icon" :size="20" />
			<span class="poll-card__header-name">{{ name }}</span>
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
import { vIntersectionObserver as IntersectionObserver } from '@vueuse/components'

import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconPencil from 'vue-material-design-icons/Pencil.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'

import { t, n } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

import { POLL } from '../../../../../constants.ts'
import { hasTalkFeature } from '../../../../../services/CapabilitiesManager.ts'
import { usePollsStore } from '../../../../../stores/polls.ts'

export default {
	name: 'Poll',

	components: {
		NcButton,
		IconDelete,
		IconPencil,
		IconPoll,
	},

	directives: {
		IntersectionObserver,
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

		draft: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['click'],

	setup() {
		return {
			pollsStore: usePollsStore(),
		}
	},

	computed: {
		poll() {
			return !this.draft
				? this.pollsStore.getPoll(this.token, this.id)
				: this.pollsStore.drafts[this.token][this.id]
		},

		pollFooterText() {
			if (this.poll?.status === POLL.STATUS.OPEN) {
				return this.poll?.votedSelf.length > 0
					? t('spreed', 'Open poll • You voted already')
					: t('spreed', 'Open poll • Click to vote')
			} else if (this.draft) {
				return n('spreed', 'Poll draft • %n option', 'Poll draft • %n options', this.poll?.options?.length)
			} else {
				return this.poll?.status === POLL.STATUS.CLOSED
					? t('spreed', 'Poll • Ended')
					: t('spreed', 'Poll')
			}
		},

		canEditPollDraft() {
			return this.draft && hasTalkFeature(this.token, 'edit-draft-poll')
		},
	},

	methods: {
		t,
		n,
		getPollData() {
			if (!this.poll) {
				this.pollsStore.getPollData({
					token: this.token,
					pollId: this.id,
				})
			}
		},

		openDraft() {
			this.$emit('click', { id: this.id, action: 'fill' })
		},

		editDraft() {
			this.$emit('click', { id: this.id, action: 'edit' })
		},

		deleteDraft() {
			this.pollsStore.deletePollDraft({
				token: this.token,
				pollId: this.id,
			})
		},

		openPoll() {
			this.pollsStore.setActivePoll({
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
	position: relative;
	display: block;
	max-width: 300px;
	padding: calc(3 * var(--default-grid-baseline)) calc(2 * var(--default-grid-baseline));
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
		gap: calc(2 * var(--default-grid-baseline));
		margin-bottom: 16px;
		font-weight: bold;
		white-space: normal;
		word-wrap: anywhere;

		&--draft {
			gap: var(--default-grid-baseline);
		}

		&-name {
			margin-inline-end: auto;
			align-self: center;
		}

		&-icon {
			height: var(--default-clickable-area);
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
