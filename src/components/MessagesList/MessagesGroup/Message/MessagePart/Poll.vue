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
		<a v-observe-visibility="getPollData"
			:aria-label="t('spreed', 'poll')"
			class="poll"
			role="button"
			@click="showModal = true">
			<div class="poll__header">
				<PollIcon :size="20" />
				<p>
					{{ pollName }}
				</p>
			</div>
			<div class="poll__footer">
				{{ t('spreed', 'Poll ・ Click to vote') }}
			</div>
		</a>

		<!-- voting and results dialog -->
		<Modal v-if="vote !== undefined && showModal"
			size="small"
			@close="showModal = false">
			<div class="poll__modal">
				<!-- First screen, displayed while voting-->
				<template v-if="modalPage === 'voting'">
					<!-- Title -->
					<h2 class="poll__modal-title">
						{{ pollName }}
					</h2>

					<!-- options -->
					<div class="poll__modal-options">
						<template v-if="checkboxRadioSwitchType === 'radio'">
							<CheckboxRadioSwitch v-for="option, index in options"
								:key="'radio' + index"
								:checked.sync="vote"
								class="poll__option"
								:value="index.toString()"
								:type="checkboxRadioSwitchType"
								name="answerType">
								{{ option }}
							</CheckboxRadioSwitch>
						</template>
						<template v-else>
							<CheckboxRadioSwitch v-for="option, index in options"
								:key="'checkbox' + index"
								:checked.sync="vote"
								:value="index.toString()"
								:type="checkboxRadioSwitchType"
								name="answerType">
								{{ option }}
							</CheckboxRadioSwitch>
						</template>
					</div>

					<div class="poll__modal-actions">
						<ButtonVue type="tertiary" @click="dismissModal">
							{{ t('spreed', 'Dismiss') }}
						</ButtonVue>
						<!-- create poll button-->
						<ButtonVue type="primary" :disabled="!canSubmitVote" @click="submitVote">
							{{ t('spreed', 'Submit') }}
						</ButtonVue>
					</div>
				</template>

				<!-- Results page -->
				<template v-if="modalPage === 'results'">
					<div>
						results
					</div>
				</template>
			</div>
		</modal>
	</div>
</template>

<script>

import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import ButtonVue from '@nextcloud/vue/dist/Components/Button'
import PollIcon from 'vue-material-design-icons/Poll.vue'

export default {

	name: 'Poll',

	components: {
		CheckboxRadioSwitch,
		Modal,
		ButtonVue,
		PollIcon,
	},

	props: {
		pollName: {
			type: String,
			required: true,
		},

		id: {
			type: Number,
			required: true,
		},

		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			vote: undefined,
			showModal: false,
			modalPage: 'voting',
		}
	},

	computed: {

		poll() {
			return this.$store.getters.getPoll(this.token, this.id)
		},

		pollLoaded() {
			return !!this.poll
		},

		votersNumber() {
			return this.pollLoaded ? this.poll.numVoters : undefined
		},

		question() {
			return this.pollLoaded ? this.poll.question : undefined
		},

		options() {
			return this.pollLoaded ? this.poll.options : undefined
		},

		pollVotes() {
			return this.pollLoaded ? this.poll.votes : undefined
		},

		selfHasVoted() {
			return this.pollLoaded ? this.poll.votedSelf : undefined
		},

		resultMode() {
			return this.pollLoaded ? this.poll.resultMode : undefined
		},

		status() {
			return this.pollLoaded ? this.poll.status : undefined
		},

		checkboxRadioSwitchType() {
			return this.poll.maxVotes === 0 ? 'checkbox' : 'radio'
		},

		canSubmitVote() {
			return this.vote !== undefined && this.vote !== '' && this.vote !== []
		},
	},

	watch: {

		pollLoaded() {
			this.setComponentData()
		},

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

		setComponentData() {
			if (this.checkboxRadioSwitchType === 'radio') {
				this.vote = ''
			} else {
				this.vote = []
			}
		},

		dismissModal() {
			this.showModal = false
			// Reset the data
			typeof this.vote === 'string' ? this.vote = '' : this.vote = []
		},

		submitVote() {
			let voteToSubmit = this.vote
			// If its a radio, we add the selected index to the array
			if (!Array.isArray(this.vote)) {
				voteToSubmit = [this.vote]
			}
			this.$store.dispatch('submitVote', {
				token: this.token,
				pollId: this.id,
				vote: voteToSubmit.map(element => parseInt(element)),
			})
			this.showModal = false
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
	transition: box-shadow 0.1s ease-in-out;
	border: 1px solid var(--color-border);
	box-shadow: 0 0 2px 0 var(--color-box-shadow);
	margin: 4px 0;
	max-width: 300px;
	padding: 16px;
	flex-direction: column;
	background: var(--color-main-background);
	border-radius: var(--border-radius-large);
	justify-content: space-between;

	&__header {
		display: flex;
		font-weight: bold;
		gap: 8px;
		white-space: normal;
		align-items: flex-start;
		span {
			margin-bottom: auto;
		}

	}
	&__footer {
		color: var(--color-text-lighter);
	}

	&__modal {
		position: relative;
	}

	&__modal-title {
		position: sticky;
		top: 0;
		font-size: 18px;
		font-weight: bold;
		padding: 20px 20px 8px 20px;
		background-color: var(--color-main-background)
	}

	&__modal-options {
		padding: 0 20px;
	}

	&__modal-actions {
		position: sticky;
		bottom: 0;
		display: flex;
		justify-content: center;
		gap: 4px;
		padding: 12px 0 16px 0;
		background-color: var(--color-main-background)
	}

}

// Upstream
::v-deep .checkbox-radio-switch {
	&__label {
		align-items: unset;
		height: unset;
		margin: 4px 0;
		padding: 8px;
		width: 100%;
		border-radius: var(--border-radius-large);
		span {
			align-self: flex-start;
		}
	}
}
</style>
