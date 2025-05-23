<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal v-if="id"
		size="small"
		:label-id="dialogHeaderId"
		@close="dismissModal">
		<div v-if="poll" class="poll-modal">
			<div class="poll-modal__header">
				<IconPoll :size="20" />
				<span :id="dialogHeaderId" role="heading" aria-level="2">
					{{ name }}
				</span>
			</div>
			<p class="poll-modal__summary">
				{{ pollSummaryText }}
			</p>

			<!-- options -->
			<div v-if="modalPage === 'voting'" class="poll-modal__options">
				<NcCheckboxRadioSwitch v-for="(option, index) in poll.options"
					:key="'option-' + index"
					v-model="checked"
					:value="index.toString()"
					:type="isMultipleAnswers ? 'checkbox' : 'radio'"
					name="answerType">
					{{ option }}
				</NcCheckboxRadioSwitch>
			</div>

			<!-- results -->
			<div v-else-if="modalPage === 'results'" class="results__options">
				<div v-for="(option, index) in poll.options"
					:key="index"
					class="results__option">
					<div class="results__option-title">
						<p>{{ option }}</p>
						<p v-if="hasVotesToDisplay" class="percentage">
							{{ votePercentage[index] + '%' }}
						</p>
					</div>
					<div v-if="getFilteredDetails(index).length > 0 || selfHasVotedOption(index)"
						class="results__option__details">
						<PollVotersDetails v-if="poll.details"
							:token="token"
							:details="getFilteredDetails(index)" />
						<p v-if="selfHasVotedOption(index)" class="results__option-subtitle">
							<IconCheck :size="16" />
							{{ t('spreed', 'You voted for this option') }}
						</p>
					</div>
					<NcProgressBar v-if="hasVotesToDisplay"
						class="results__option-progress"
						:value="votePercentage[index]"
						size="medium" />
				</div>
			</div>

			<div v-if="isPollOpen" class="poll-modal__actions">
				<!-- Submit vote button-->
				<NcButton v-if="modalPage === 'voting'"
					type="primary"
					:disabled="disabled"
					@click="submitVote">
					{{ t('spreed', 'Submit vote') }}
				</NcButton>
				<!-- Vote again-->
				<NcButton v-else
					type="secondary"
					@click="modalPage = 'voting'">
					{{ t('spreed', 'Change your vote') }}
				</NcButton>
				<NcActions v-if="canEndPoll" force-menu>
					<NcActionButton v-if="supportPollDrafts && isModerator" @click="createPollDraft">
						<template #icon>
							<IconFileEdit :size="20" />
						</template>
						{{ t('spreed', 'Save as draft') }}
					</NcActionButton>
					<NcActionLink v-if="supportPollDrafts" :href="exportPollURI" :download="exportPollFileName">
						<template #icon>
							<IconFileDownload :size="20" />
						</template>
						{{ t('spreed', 'Export draft to file') }}
					</NcActionLink>
					<NcActionButton class="critical" @click="endPoll">
						{{ t('spreed', 'End poll') }}
						<template #icon>
							<IconFileLock :size="20" />
						</template>
					</NcActionButton>
				</NcActions>
			</div>
			<div v-else-if="supportPollDrafts && selfIsOwnerOrModerator" class="poll-modal__actions">
				<NcActions force-menu>
					<NcActionButton v-if="isModerator" @click="createPollDraft">
						<template #icon>
							<IconFileEdit :size="20" />
						</template>
						{{ t('spreed', 'Save as draft') }}
					</NcActionButton>
					<NcActionLink :href="exportPollURI" :download="exportPollFileName">
						<template #icon>
							<IconFileDownload :size="20" />
						</template>
						{{ t('spreed', 'Export draft to file') }}
					</NcActionLink>
				</NcActions>
			</div>
		</div>
		<NcLoadingIcon v-else class="poll-modal__loading" />
	</NcModal>
</template>

<script>
import { n, t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconFileDownload from 'vue-material-design-icons/FileDownload.vue'
import IconFileEdit from 'vue-material-design-icons/FileEdit.vue'
import IconFileLock from 'vue-material-design-icons/FileLock.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import PollVotersDetails from './PollVotersDetails.vue'
import { useId } from '../../composables/useId.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { POLL } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { usePollsStore } from '../../stores/polls.ts'
import { calculateVotePercentage } from '../../utils/calculateVotePercentage.ts'
import { convertToJSONDataURI } from '../../utils/fileDownload.ts'

export default {
	name: 'PollViewer',

	components: {
		NcActions,
		NcActionButton,
		NcActionLink,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcModal,
		NcButton,
		NcProgressBar,
		PollVotersDetails,
		// icons
		IconCheck,
		IconFileLock,
		IconFileDownload,
		IconFileEdit,
		IconPoll,
	},

	setup() {
		const voteToSubmit = ref([])
		const modalPage = ref('')
		const loading = ref(false)
		const dialogHeaderId = `guest-welcome-header-${useId()}`

		const pollsStore = usePollsStore()
		const activePoll = computed(() => pollsStore.activePoll)
		const name = computed(() => activePoll.value?.name)
		const id = computed(() => activePoll.value?.id)
		const token = computed(() => activePoll.value?.token)

		const poll = computed(() => pollsStore.getPoll(token.value, id.value))
		const supportPollDrafts = computed(() => hasTalkFeature(token.value, 'talk-polls-drafts'))

		const exportPollURI = computed(() => convertToJSONDataURI({
			question: poll.value.question,
			options: poll.value.options,
			resultMode: poll.value.resultMode,
			maxVotes: poll.value.maxVotes,
		}))
		const exportPollFileName = `Talk Poll ${new Date().toISOString().slice(0, 10)}`

		return {
			isInCall: useIsInCall(),
			pollsStore,
			voteToSubmit,
			modalPage,
			loading,
			dialogHeaderId,
			name,
			id,
			token,
			poll,
			supportPollDrafts,
			exportPollURI,
			exportPollFileName,
		}
	},

	computed: {
		selfHasVoted() {
			return this.poll?.votedSelf?.length > 0
		},

		isPollPublic() {
			return this.poll?.resultMode === POLL.MODE.PUBLIC
		},

		isPollOpen() {
			return this.poll?.status === POLL.STATUS.OPEN
		},

		isPollClosed() {
			return this.poll?.status === POLL.STATUS.CLOSED
		},

		isMultipleAnswers() {
			return this.poll?.maxVotes === POLL.ANSWER_TYPE.MULTIPLE
		},

		checked: {
			get() {
				return this.voteToSubmit
			},

			set(value) {
				this.voteToSubmit = Array.isArray(value) ? value : [value]
			},
		},

		disabled() {
			return this.loading || this.voteToSubmit.length === 0
		},

		isModerator() {
			return this.$store.getters.isModerator
		},

		selfIsOwnerOrModerator() {
			return this.isModerator
				|| (this.poll?.actorType === this.$store.getters.getActorType()
					&& this.poll?.actorId === this.$store.getters.getActorId())
		},

		pollSummaryText() {
			if (this.isPollClosed) {
				return n('spreed', 'Poll results • %n vote', 'Poll results • %n votes', this.poll?.numVoters)
			}

			if (this.isPollPublic && (this.selfIsOwnerOrModerator || this.selfHasVoted)) {
				return n('spreed', 'Open poll • %n vote', 'Open poll • %n votes', this.poll?.numVoters)
			}

			if (!this.isPollPublic && this.selfHasVoted) {
				return t('spreed', 'Open poll • You voted already')
			}

			return t('spreed', 'Open poll')
		},

		canEndPoll() {
			return this.isPollOpen && this.selfIsOwnerOrModerator
		},

		hasVotesToDisplay() {
			return Object.keys(Object(this.poll?.votes)).length !== 0
		},

		votePercentage() {
			const votes = Object.keys(Object(this.poll?.options)).map((index) => this.poll?.votes['option-' + index] ?? 0)
			return calculateVotePercentage(votes, this.poll.numVoters)
		},
	},

	watch: {
		modalPage(value) {
			if (value === 'voting') {
				this.setVoteData()
			}
		},

		id(value) {
			this.pollsStore.hidePollToast(value)
		},

		isInCall(value) {
			if (!value) {
				this.pollsStore.hideAllPollToasts()
			}
		},

		poll: {
			immediate: true,
			handler(value) {
				if (!value) {
					this.modalPage = ''
				} else if (this.selfHasVoted || this.isPollClosed) {
					this.modalPage = 'results'
				} else {
					this.modalPage = 'voting'
				}
			},
		},
	},

	mounted() {
		EventBus.on('talk:poll-added', this.showPollToast)
	},

	beforeDestroy() {
		EventBus.off('talk:poll-added', this.showPollToast)
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

		setVoteData() {
			this.voteToSubmit = this.selfHasVoted
				? this.poll?.votedSelf.map((el) => el.toString())
				: []
		},

		showPollToast({ token, message }) {
			if (!this.isInCall) {
				return
			}

			this.pollsStore.addPollToast({ token, message })
		},

		dismissModal() {
			this.pollsStore.removeActivePoll()
			this.voteToSubmit = []
		},

		async submitVote() {
			this.loading = true
			try {
				await this.pollsStore.submitVote({
					token: this.token,
					pollId: this.id,
					optionIds: this.voteToSubmit.map((element) => +element),
				})
				this.modalPage = 'results'
			} catch (error) {
				console.error(error)
				this.modalPage = 'voting'
			}
			this.loading = false
		},

		async endPoll() {
			this.loading = true
			try {
				await this.pollsStore.endPoll({
					token: this.token,
					pollId: this.id,
				})
				this.modalPage = 'results'
			} catch (error) {
				console.error(error)
			}
			this.loading = false
		},

		async createPollDraft() {
			await this.pollsStore.createPollDraft({
				token: this.token,
				form: this.poll,
			})
		},

		selfHasVotedOption(index) {
			return this.poll?.votedSelf.includes(index)
		},

		getFilteredDetails(index) {
			return (this.poll?.details || []).filter((item) => item.optionId === index)
		},
	},
}
</script>

<style lang="scss" scoped>
.poll-modal {
	position: relative;
	padding: 20px;

	&__header {
		display: flex;
		align-items: flex-start;
		gap: 8px;
		margin-bottom: 8px;
		font-weight: bold;
		font-size: 18px;
		white-space: normal;
		word-wrap: anywhere;

		:deep(.material-design-icon) {
			margin-bottom: auto;
		}
	}

	&__summary {
		color: var(--color-text-maxcontrast);
		margin-bottom: 16px;
	}

	&__options {
		display: flex;
		flex-direction: column;
		gap: 4px;
		word-wrap: anywhere;
	}

	&__actions {
		position: sticky;
		bottom: 0;
		display: flex;
		justify-content: center;
		gap: 8px;
		padding: 8px 0 0;
		background-color: var(--color-main-background);
	}

	&__loading {
		height: 200px;
	}
}

.results__options {
	display: flex;
	flex-direction: column;
	gap: calc(4 * var(--default-grid-baseline));
	word-wrap: anywhere;
	margin: 8px 0 20px 0;
}

.results__option {
	display: flex;
	flex-direction: column;

	&:not(:last-child) {
		border-bottom: 1px solid var(--color-border);
	}

	&__details {
		display: flex;
		margin-bottom: 8px;
	}

	&-subtitle {
		display: flex;
		gap: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
	}

	&-progress {
		margin-top: 4px;
	}

	&-title {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 4px;

		.percentage {
			white-space: nowrap;
			margin-inline-start: 16px;
		}
	}
}

.critical :deep(.action-button) {
	color: var(--color-error) !important;
}
</style>
