<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="wrapper">
		<!-- Poll card -->
		<a v-if="!showAsButton"
			v-observe-visibility="getPollData"
			:aria-label="t('spreed', 'Poll')"
			class="poll"
			role="button"
			@click="openPoll">
			<div class="poll__header">
				<PollIcon :size="20" />
				<p>
					{{ name }}
				</p>
			</div>
			<div class="poll__footer">
				{{ pollFooterText }}
			</div>

		</a>

		<!-- Poll results button in system message -->
		<div v-else class="poll-closed">
			<NcButton type="secondary" @click="openPoll">
				{{ t('spreed', 'See results') }}
			</NcButton>
		</div>

		<!-- voting and results dialog -->
		<NcModal v-if="vote !== undefined && showModal"
			size="small"
			:container="container"
			@close="dismissModal">
			<div class="poll__modal">
				<!-- Title -->
				<div class="poll__header">
					<PollIcon :size="20" />
					<h2 class="poll__modal-title">
						{{ name }}
					</h2>
				</div>
				<p class="poll__summary">
					{{ pollSummaryText }}
				</p>

				<!-- options -->
				<div v-if="modalPage === 'voting'" class="poll__modal-options">
					<template v-if="checkboxRadioSwitchType">
						<NcCheckboxRadioSwitch v-for="(option, index) in options"
							:key="checkboxRadioSwitchType + index"
							:checked.sync="vote"
							class="poll__option"
							:value="index.toString()"
							:type="checkboxRadioSwitchType"
							name="answerType">
							{{ option }}
						</NcCheckboxRadioSwitch>
					</template>
				</div>

				<!-- results -->
				<div v-else-if="modalPage === 'results'" class="results__options">
					<div v-for="(option, index) in options"
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
							<PollVotersDetails v-if="details"
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

				<div v-if="isPollOpen" class="poll__modal-actions">
					<!-- Submit vote button-->
					<NcButton v-if="modalPage === 'voting'"
						type="primary"
						:disabled="!canSubmitVote"
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
		</NcModal>
	</div>
</template>

<script>

import FileLock from 'vue-material-design-icons/FileLock.vue'
import PollIcon from 'vue-material-design-icons/Poll.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'

import PollVotersDetails from './PollVotersDetails.vue'

import { PARTICIPANT } from '../../../../../constants.js'

export default {

	name: 'Poll',

	components: {
		NcActions,
		NcActionButton,
		NcCheckboxRadioSwitch,
		NcModal,
		NcButton,
		NcProgressBar,
		PollVotersDetails,
		// icons
		FileLock,
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

	data() {
		return {
			vote: undefined,
			showModal: false,
			modalPage: '',
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		poll() {
			return this.$store.getters.getPoll(this.token, this.id)
		},

		pollLoaded() {
			return !!this.poll
		},

		numVoters() {
			return this.pollLoaded ? this.poll.numVoters : undefined
		},

		question() {
			return this.pollLoaded ? this.poll.question : undefined
		},

		options() {
			return this.pollLoaded ? this.poll.options : undefined
		},

		votes() {
			return this.pollLoaded ? this.poll.votes : undefined
		},

		selfHasVoted() {
			if (this.pollLoaded) {
				if (typeof this.votedSelf === 'object') {
					return this.votedSelf.length > 0
				} else {
					return !!this.votedSelf
				}
			} else {
				return undefined
			}
		},

		// The actual vote of the user as returned from the server
		votedSelf() {
			return this.pollLoaded ? this.poll.votedSelf : undefined
		},

		resultMode() {
			return this.pollLoaded ? this.poll.resultMode : undefined
		},

		isPollPublic() {
			return this.resultMode === 0
		},

		status() {
			return this.pollLoaded ? this.poll.status : undefined
		},

		isPollOpen() {
			return this.status === 0
		},

		isPollClosed() {
			return this.status === 1
		},

		details() {
			if (!this.pollLoaded || this.isPollOpen) {
				return undefined
			} else {
				return this.poll.details
			}
		},

		checkboxRadioSwitchType() {
			if (this.pollLoaded) {
				return this.poll.maxVotes === 0 ? 'checkbox' : 'radio'
			} else {
				return undefined
			}
		},

		canSubmitVote() {
			if (typeof this.vote === 'object') {
				return this.vote.length > 0
			} else {
				return this.vote !== undefined && this.vote !== ''
			}
		},

		participantType() {
			return this.$store.getters.conversation(this.token).participantType
		},

		selfIsOwnerOrModerator() {
			return (this.poll.actorType === this.$store.getters.getActorType() && this.poll.actorId === this.$store.getters.getActorId())
				|| [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(this.participantType)
		},

		pollSummaryText() {
			if (this.isPollClosed) {
				return n('spreed', 'Poll results • %n vote', 'Poll results • %n votes', this.numVoters)
			}

			if (this.selfIsOwnerOrModerator || (this.isPollPublic && this.selfHasVoted)) {
				return n('spreed', 'Open poll • %n vote', 'Open poll • %n votes', this.numVoters)
			}

			if (!this.isPollPublic && this.selfHasVoted) {
				return t('spreed', 'Open poll • You voted already')
			}

			return t('spreed', 'Open poll')
		},

		canEndPoll() {
			return this.isPollOpen && this.selfIsOwnerOrModerator
		},

		pollFooterText() {
			if (this.isPollOpen) {
				return this.selfHasVoted ? t('spreed', 'Open poll • You voted already') : t('spreed', 'Open poll • Click to vote')
			} else if (this.isPollClosed) {
				return t('spreed', 'Poll • Ended')
			}
			return t('spreed', 'Poll')
		},
	},

	watch: {
		pollLoaded() {
			this.setVoteData()
		},

		modalPage(value) {
			if (value === 'voting') {
				this.setVoteData()
			}
		},

	},

	mounted() {
		this.setVoteData()
	},

	methods: {
		getPollData() {
			if (!this.pollLoaded) {
				this.$store.dispatch('getPollData', {
					token: this.token,
					pollId: this.id,
				})
			}
		},

		setVoteData() {
			if (this.checkboxRadioSwitchType === 'radio') {
				this.vote = ''
				if (this.selfHasVoted) {
					this.vote = this.votedSelf[0].toString()
				}
			} else {
				this.vote = []
				if (this.selfHasVoted) {
					this.vote = this.votedSelf.map(element => element.toString())
				}
			}
		},

		openPoll() {
			if (this.selfHasVoted || this.isPollClosed) {
				this.modalPage = 'results'
			} else {
				this.modalPage = 'voting'
			}
			this.showModal = true
		},

		dismissModal() {
			this.showModal = false
			// Reset the data
			typeof this.vote === 'string' ? this.vote = '' : this.vote = []
		},

		submitVote() {
			let voteToSubmit = this.vote
			// If it's a radio, we add the selected index to the array
			if (!Array.isArray(this.vote)) {
				voteToSubmit = [this.vote]
			}
			this.$store.dispatch('submitVote', {
				token: this.token,
				pollId: this.id,
				vote: voteToSubmit.map(element => parseInt(element)),
			})
			this.modalPage = 'results'
		},

		endPoll() {
			this.$store.dispatch('endPoll', {
				token: this.token,
				pollId: this.id,
			})
			this.modalPage = 'results'
		},

		selfHasVotedOption(index) {
			return this.votedSelf.includes(index)
		},

		getFilteredDetails(index) {
			if (!this.details) {
				return []
			}
			return this.details.filter((item) => {
				return item.optionId === index
			})
		},

		getVotePercentage(index) {
			if (this.votes[`option-${index}`] === undefined) {
				return 0
			}
			return parseInt(this.votes[`option-${index}`] / this.numVoters * 100)
		},
	},
}
</script>

<style lang="scss" scoped>
.wrapper {
	display: contents;
}

.poll {
	display: flex;
	border: 2px solid var(--color-border);
	max-width: 300px;
	padding: 0 16px 16px 16px;
	flex-direction: column;
	background: var(--color-main-background);
	border-radius: var(--border-radius-large);
	justify-content: space-between;
	transition: border-color 0.1s ease-in-out;

	&:hover,
	&:focus,
	&:focus-visible {
		border-color: var(--color-primary-element);
		outline: none;
	}

	&__header {
		display: flex;
		font-weight: bold;
		gap: 8px;
		white-space: normal;
		align-items: flex-start;
		top: 0;
		padding: 20px 0 8px;
		word-wrap: anywhere;

		span {
			margin-bottom: auto;
		}

	}

	&__footer {
		color: var(--color-text-maxcontrast);
		white-space: normal;
		margin-top: 8px;
	}

	&__modal {
		position: relative;
		padding: 20px;
	}

	&__modal-title {
		margin: 0;
		font-size: 18px;
		font-weight: bold;
	}

	&__modal-options {
		word-wrap: anywhere;
		margin-top: 8px;
	}

	&__modal-actions {
		position: sticky;
		bottom: 0;
		display: flex;
		justify-content: center;
		gap: 8px;
		padding: 8px 0 0;
		background-color: var(--color-main-background);
	}

	&__summary {
		color: var(--color-text-maxcontrast);
		margin-bottom: 16px;
	}

	&__option {
		margin-bottom: 4px;
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
}

.results__option-title {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 4px;

	.percentage {
		white-space: nowrap;
		margin-left: 16px;
	}
}

.poll-closed {
	display: flex;
	justify-content: center;
	margin-top: 4px;
}

.critical {
	:deep(.action-button) {
		color: var(--color-error) !important;
	}
}
</style>
