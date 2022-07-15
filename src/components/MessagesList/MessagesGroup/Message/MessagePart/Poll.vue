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
	<div v-observe-visibility="getPollData">
		Poll
		<p>{{ pollName }}</p>
		<template v-if="vote !== undefined">
			<template v-if="checkboxRadioSwitchType === 'radio'">
				<CheckboxRadioSwitch v-for="option, index in options"
					:key="'radio' + index"
					:checked.sync="vote"
					:value="option"
					:type="checkboxRadioSwitchType"
					name="answerType">
					{{ option }}
				</CheckboxRadioSwitch>
			</template>
			<template v-else>
				<CheckboxRadioSwitch v-for="option, index in options"
					:key="'checkbox' + index"
					:checked.sync="vote"
					:value="option"
					:type="checkboxRadioSwitchType"
					name="answerType">
					{{ option }}
				</CheckboxRadioSwitch>
			</template>
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

	data() {
		return {
			vote: undefined,
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
	},
}
</script>

<style lang="scss" scoped>

</style>
