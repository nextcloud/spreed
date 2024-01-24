<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
</template>

<script>
import PollIcon from 'vue-material-design-icons/Poll.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

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

		pollLoaded() {
			return !!this.poll
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

		status() {
			return this.pollLoaded ? this.poll.status : undefined
		},

		isPollOpen() {
			return this.status === 0
		},

		isPollClosed() {
			return this.status === 1
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

	methods: {
		getPollData() {
			if (!this.pollLoaded) {
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
}

.poll-closed {
	display: flex;
	justify-content: center;
	margin-top: 4px;
}
</style>
