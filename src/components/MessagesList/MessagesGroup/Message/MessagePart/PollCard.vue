<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="draft" class="poll-card" @click="openDraft">
		<span class="poll-card__header poll-card__header--draft">
			<IconChartBoxOutline :size="20" />
			<span class="poll-card__header-name">{{ name }}</span>
			<NcButton
				v-if="canEditPollDraft"
				variant="tertiary"
				:title="t('spreed', 'Edit poll draft')"
				:aria-label="t('spreed', 'Edit poll draft')"
				@click.stop="editDraft">
				<template #icon>
					<IconPencilOutline :size="20" />
				</template>
			</NcButton>
			<NcButton
				variant="tertiary"
				:title="t('spreed', 'Delete poll draft')"
				:aria-label="t('spreed', 'Delete poll draft')"
				@click.stop="deleteDraft">
				<template #icon>
					<IconTrashCanOutline :size="20" />
				</template>
			</NcButton>
		</span>
		<span class="poll-card__footer">
			{{ pollFooterText }}
		</span>
	</div>
	<a
		v-else-if="!showAsButton"
		v-intersection-observer="getPollData"
		:aria-label="t('spreed', 'Poll')"
		class="poll-card"
		role="button"
		@click="openPoll">
		<div class="poll-card__status">
			<IconChartBoxOutline :size="20" />
			<NcChip
				v-if="showPollStatus"
				:variant="pollChipVariant"
				noClose>
				{{ isPollOpen ? t('spreed', 'Open poll') : t('spreed', 'Closed poll') }}
			</NcChip>
		</div>
		<div class="poll-card__header">{{ name }}</div>
		<span class="poll-card__footer">
			<IconCheck v-if="isPollOpen && poll?.votedSelf.length > 0" :size="12" />
			{{ pollFooterText }}
		</span>
	</a>

	<!-- Poll results button in system message -->
	<NcButton
		v-else
		class="poll-closed"
		variant="secondary"
		@click="openPoll">
		{{ t('spreed', 'See results') }}
	</NcButton>
</template>

<script>
import { n, t } from '@nextcloud/l10n'
import { vIntersectionObserver as IntersectionObserver } from '@vueuse/components'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import IconChartBoxOutline from 'vue-material-design-icons/ChartBoxOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { POLL } from '../../../../../constants.ts'
import { hasTalkFeature } from '../../../../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../../../../stores/actor.ts'
import { usePollsStore } from '../../../../../stores/polls.ts'

export default {
	name: 'PollCard',

	components: {
		NcButton,
		IconCheck,
		IconTrashCanOutline,
		IconPencilOutline,
		IconChartBoxOutline,
		NcChip,
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
			actorStore: useActorStore(),
			POLL,
		}
	},

	computed: {
		poll() {
			return !this.draft
				? this.pollsStore.getPoll(this.token, this.id)
				: this.pollsStore.drafts[this.token][this.id]
		},

		isPollPublic() {
			return this.poll?.resultMode === POLL.MODE.PUBLIC
		},

		selfIsOwnerOrModerator() {
			return this.$store.getters.isModerator
				|| (this.poll && this.actorStore.checkIfSelfIsActor(this.poll))
		},

		selfHasVoted() {
			return this.poll?.votedSelf.length > 0
		},

		isPollOpen() {
			return this.poll?.status === POLL.STATUS.OPEN
		},

		pollFooterText() {
			if (this.isPollOpen) {
				return this.selfHasVoted
					? this.isPollPublic || this.selfIsOwnerOrModerator
						? n('spreed', 'You voted • %n vote', 'You voted • %n votes', this.poll?.numVoters)
						: t('spreed', 'You voted')
					: this.selfIsOwnerOrModerator
						? n('spreed', 'Click to vote • %n vote', 'Click to vote • %n votes', this.poll?.numVoters)
						: t('spreed', 'Click to vote')
			} else if (this.draft) {
				return n('spreed', 'Poll draft • %n option', 'Poll draft • %n options', this.poll?.options?.length)
			} else {
				return n('spreed', '%n vote', '%n votes', this.poll?.numVoters || 0)
			}
		},

		canEditPollDraft() {
			return this.draft && hasTalkFeature(this.token, 'edit-draft-poll')
		},

		showPollStatus() {
			return this.poll && this.poll.status !== POLL.STATUS.DRAFT
		},

		pollChipVariant() {
			if (this.isPollOpen) {
				return this.selfHasVoted ? 'secondary' : 'primary'
			} else {
				return 'tertiary'
			}
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
	padding: calc(2 * var(--default-grid-baseline));
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

	.poll-card__status {
		min-height: calc( 24px + var(--default-grid-baseline));
		display: flex;
		gap: var(--default-grid-baseline);
		padding-bottom: var(--default-grid-baseline);
	}

	&__header {
		margin-bottom: calc(4 * var(--default-grid-baseline));
		font-weight: bold;
		white-space: normal;
		overflow-wrap: anywhere;

		&-name {
			margin-inline-end: auto;
		}

		&--draft {
			display: flex;
			align-items: center;
			gap: var(--default-grid-baseline);
		}
	}

	&__footer {
		display: inline-flex;
		gap: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
		white-space: normal;
	}
}

.poll-closed {
	margin: 4px auto;
}
</style>
