<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
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
	<NcModal v-if="id"
		size="small"
		:container="container"
		@close="dismissModal">
		<div v-if="poll" class="poll-modal">
			<div class="poll-modal__header">
				<PollIcon :size="20" />
				<span role="heading" aria-level="2">
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
					:checked.sync="checked"
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
						<p class="percentage">
							{{ getVotePercentage(index) + '%' }}
						</p>
					</div>
					<div v-if="getFilteredDetails(index).length > 0 || selfHasVotedOption(index)"
						class="results__option__details">
						<PollVotersDetails v-if="poll.details"
							:container="container"
							:details="getFilteredDetails(index)" />
						<p v-if="selfHasVotedOption(index)" class="results__option-subtitle">
							{{ t('spreed', 'You voted for this option') }}
						</p>
					</div>
					<NcProgressBar class="results__option-progress"
						:value="getVotePercentage(index)"
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
				<!-- End poll button-->
				<NcActions v-if="canEndPoll"
					force-menu
					:container="container">
					<NcActionButton class="critical" @click="endPoll">
						{{ t('spreed', 'End poll') }}
						<template #icon>
							<FileLock :size="20" />
						</template>
					</NcActionButton>
				</NcActions>
			</div>
		</div>
		<NcLoadingIcon v-else class="poll-modal__loading" />
	</NcModal>
</template>

<script>
import FileLock from 'vue-material-design-icons/FileLock.vue'
import PollIcon from 'vue-material-design-icons/Poll.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'

import PollVotersDetails from './PollVotersDetails.vue'

import { POLL } from '../../constants.js'

export default {
	name: 'PollViewer',

	components: {
		NcActions,
		NcActionButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcModal,
		NcButton,
		NcProgressBar,
		PollVotersDetails,
		// icons
		FileLock,
		PollIcon,
	},

	data() {
		return {
			voteToSubmit: [],
			modalPage: '',
			loading: false,
		}
	},

	computed: {
		activePoll() {
			return this.$store.getters.activePoll
		},

		name() {
			return this.activePoll?.name
		},

		id() {
			return this.activePoll?.id
		},

		token() {
			return this.activePoll?.token
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		poll() {
			return this.$store.getters.getPoll(this.token, this.id)
		},

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

		selfIsOwnerOrModerator() {
			return this.$store.getters.isModerator
				|| (this.poll?.actorType === this.$store.getters.getActorType()
					&& this.poll?.actorId === this.$store.getters.getActorId())
		},

		pollSummaryText() {
			if (this.isPollClosed) {
				return n('spreed', 'Poll results • %n vote', 'Poll results • %n votes', this.poll?.numVoters)
			}

			if (this.selfIsOwnerOrModerator || (this.isPollPublic && this.selfHasVoted)) {
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
	},

	watch: {
		modalPage(value) {
			if (value === 'voting') {
				this.setVoteData()
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

	methods: {
		getPollData() {
			if (!this.poll) {
				this.$store.dispatch('getPollData', {
					token: this.token,
					pollId: this.id,
				})
			}
		},

		setVoteData() {
			this.voteToSubmit = this.selfHasVoted
				? this.poll?.votedSelf.map(el => el.toString())
				: []
		},

		dismissModal() {
			this.$store.dispatch('removeActivePoll')
			this.voteToSubmit = []
		},

		async submitVote() {
			this.loading = true
			try {
				await this.$store.dispatch('submitVote', {
					token: this.token,
					pollId: this.id,
					vote: this.voteToSubmit.map(element => +element),
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
				await this.$store.dispatch('endPoll', {
					token: this.token,
					pollId: this.id,
				})
				this.modalPage = 'results'
			} catch (error) {
				console.error(error)
			}
			this.loading = false
		},

		selfHasVotedOption(index) {
			return this.poll?.votedSelf.includes(index)
		},

		getFilteredDetails(index) {
			return (this.poll?.details || []).filter(item => item.optionId === index)
		},

		getVotePercentage(index) {
			if (!this.poll?.votes['option-' + index] || !this.poll?.numVoters) {
				return 0
			}
			return parseInt(this.poll?.votes['option-' + index] / this.poll?.numVoters * 100)
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
	gap: 24px;
	word-wrap: anywhere;
	margin: 8px 0 20px 0;
}

.results__option {
	display: flex;
	flex-direction: column;

	&__details {
		display: flex;
		margin-bottom: 8px;
	}

	&-subtitle {
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
			margin-left: 16px;
		}
	}
}

.critical :deep(.action-button) {
	color: var(--color-error) !important;
}
</style>
