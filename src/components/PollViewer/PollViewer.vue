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
	<NcModal v-if="vote !== undefined && id"
		size="small"
		:container="container"
		@close="dismissModal">
		<div class="poll-modal">
			<!-- Title -->
			<div class="poll-modal__header">
				<PollIcon :size="20" />
				<h2 class="poll-modal__title">
					{{ name }}
				</h2>
			</div>
			<p class="poll-modal__summary">
				{{ pollSummaryText }}
			</p>

			<!-- options -->
			<div v-if="modalPage === 'voting'" class="poll-modal__options">
				<template v-if="checkboxRadioSwitchType">
					<NcCheckboxRadioSwitch v-for="(option, index) in options"
						:key="checkboxRadioSwitchType + index"
						:checked.sync="vote"
						class="poll-modal__option"
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

import { PARTICIPANT } from '../../constants.js'

export default {
	name: 'PollViewer',

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

	data() {
		return {
			vote: undefined,
			modalPage: '',
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

		pollLoaded() {
			return !!this.poll
		},

		numVoters() {
			return this.pollLoaded ? this.poll?.numVoters : undefined
		},

		question() {
			return this.pollLoaded ? this.poll?.question : undefined
		},

		options() {
			return this.pollLoaded ? this.poll?.options : undefined
		},

		votes() {
			return this.pollLoaded ? this.poll?.votes : undefined
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
			return this.pollLoaded ? this.poll?.votedSelf : undefined
		},

		resultMode() {
			return this.pollLoaded ? this.poll?.resultMode : undefined
		},

		isPollPublic() {
			return this.resultMode === 0
		},

		status() {
			return this.pollLoaded ? this.poll?.status : undefined
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
				return this.poll?.details
			}
		},

		checkboxRadioSwitchType() {
			if (this.pollLoaded) {
				return this.poll?.maxVotes === 0 ? 'checkbox' : 'radio'
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
			return (this.poll?.actorType === this.$store.getters.getActorType() && this.poll?.actorId === this.$store.getters.getActorId())
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

		dismissModal() {
			this.$store.dispatch('removeActivePoll')
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
.poll-modal {
	position: relative;
	padding: 20px;

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

	&__title {
		margin: 0;
		font-size: 18px;
		font-weight: bold;
	}

	&__options {
		word-wrap: anywhere;
		margin-top: 8px;
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

.critical {
	:deep(.action-button) {
		color: var(--color-error) !important;
	}
}
</style>
