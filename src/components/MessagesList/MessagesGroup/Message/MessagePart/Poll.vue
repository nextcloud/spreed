<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@pm.me>
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
	<div v-observe-visibility="getPollData">
		Poll
		<p>{{ pollName }}</p>
		<template v-if="pollLoaded">
			<CheckboxRadioSwitch v-for="answer, index in answers"
				:key="index"
				:checked.sync="sharingPermission"
				:value="answer"
				name="answerType"
				type="radio" />
		</template>
	</div>
</template>

<script>
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'Poll',

	components: {
		CheckboxRadioSwitch,
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

	computed: {
		poll() {
			return this.$store.getters.getPoll(this.token, this.id)
		},

		pollLoaded() {
			return !!this.poll
		},

		maxVotes() {
			return this.poll.maxVotes
		},

		votersNumber() {
			return this.poll.numVoters
		},

		question() {
			return this.poll.question
		},

		answers() {
			return this.poll.options
		},

		pollVotes() {
			return this.polls.votes
		},

		selfHasVoted() {
			return this.poll.votedSelf
		},

		resultMode() {
			return this.poll.resultMode
		},

		status() {
			return this.poll.status
		},

		isMultipleAnswers() {
			return this.poll.maxVotes === 0
		},
	},

	methods: {
		// When the poll system message becomes visible, we get the poll data.
		// Once we have a virtual scroller we can change this and get the
		// data when the poll is approaching the viewport.
		getPollData() {
			if (!this.pollLoaded) {
				this.$store.dispatch('getPollData', {
					token: this.token,
					pollId: this.id,
				})
			}

		},
	},
}
</script>

<style lang="scss" scoped>

</style>
