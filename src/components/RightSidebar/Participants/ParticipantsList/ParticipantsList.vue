<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<div>
		<ul v-if="!loading && !noResults"
			:class="{'scrollable': scrollable }"
			:style="{'height': height}">
			<Participant
				v-for="participant in participants"
				:key="participant.userId"
				:participant="participant"
				@clickParticipant="handleClickParticipant" />
		</ul>
		<template v-if="loading">
			<div class="icon-loading participants-list__icon" />
			<p class="participants-list__warning">
				{{ t('spreed', 'Contacts loading') }}
			</p>
		</template>
		<template v-if="noResults">
			<div class="icon-error participants-list__icon" />
			<p class="participants-list__warning">
				{{ t('spreed', 'No results') }}
			</p>
		</template>
	</div>
</template>

<script>

import Participant from './Participant/Participant'
import { addParticipant } from '../../../../services/participantsService'
import Vue from 'vue'

export default {
	name: 'ParticipantsList',

	components: {
		Participant,
	},

	props: {
		/**
		 * List of searched users or groups
		 */
		items: {
			type: Array,
			required: true,
		},
		/**
		 * If true, clicking the participant will add it to to the current conversation.
		 * This behavior is used in the right sidebar for already existing conversations.
		 * If false, clicking on the participant will add the participant to the
		 * `selectedParticipants` array in the data.
		 */
		addOnClick: {
			type: Boolean,
			default: true,
		},
		/**
		 * A fixed height can be passed in e.g. ('250px'). This will limit the height of
		 * the ul and make it scrollable.
		 */
		height: {
			type: String,
			default: 'auto',
		},
		/**
		 * Display loading state instead of list.
		 */
		loading: {
			type: Boolean,
			default: false,
		},
		/**
		 * Display no-results state instead of list.
		 */
		noResults: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			selectedParticipants: [],
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},
		/**
		 * Creates a new array that combines the items (participants received as a prop)
		 * with the current selectedParticipants so that each participant in the returned
		 * array has a new 'selected' boolean key.
		 * @returns {array} An array of 'participant' objects
		 */
		participants() {
			/**
			 * Compute this only in the new group conversation form.
			 */
			if (this.addOnClick === false) {
				if (this.items !== []) {
					const participants = this.items.slice()
					participants.forEach(item => {
						if (this.selectedParticipants.indexOf(item) !== -1) {
							Vue.set(item, 'selected', true)
						} else {
							Vue.set(item, 'selected', false)
						}
					})
					return participants
				} else {
					return []
				}
			} else {
				return this.items
			}
		},
		scrollable() {
			return this.height !== 'auto'
		},
	},

	methods: {
		async handleClickParticipant(participant) {
			if (this.addOnClick) {
				/**
				 * Add the clicked participant to the current conversation
				 */
				try {
					await addParticipant(this.token, participant.id, participant.source)
					this.$emit('refreshCurrentParticipants')
				} catch (exception) {
					console.debug(exception)
				}
			} else {
				/**
				 * Remove the clicked participant from the selected participants list
				 */
				if (this.selectedParticipants.indexOf(participant) !== -1) {
					this.selectedParticipants = this.selectedParticipants.filter((selectedParticipant) => {
						return selectedParticipant.id !== participant.id
					})
					this.$emit('updateSelectedParticipants', this.selectedParticipants)
				} else {
					/**
					 * Add the clicked participant from the selected participants list
					 */
					this.selectedParticipants = [...this.selectedParticipants, participant]
					this.$emit('updateSelectedParticipants', this.selectedParticipants)
				}
			}

		},
	},
}
</script>

<style lang="scss" scoped>
.scrollable {
	overflow-y: auto;
	overflow-x: hidden;
}

.participants-list {
	&__icon {
		margin-top: 40px;
	}
	&__warning {
		margin-top: 20px;
		text-align: center;
	}
}

</style>
