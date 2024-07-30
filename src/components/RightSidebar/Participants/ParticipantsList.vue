<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul v-if="!loading">
		<template v-if="isSearchResult">
			<ParticipantSelectable v-for="item in items"
				:key="generateKey(item)"
				:participant="item"
				:show-user-status="showUserStatus"
				@click-participant="handleClickParticipant" />
		</template>
		<template v-else>
			<Participant v-for="item in items"
				:key="generateKey(item)"
				:participant="item"
				:show-user-status="showUserStatus"
				@click-participant="handleClickParticipant" />
		</template>
	</ul>
	<LoadingPlaceholder v-else type="participants" :count="dummyParticipants" />
</template>

<script>

import Participant from './Participant.vue'
import ParticipantSelectable from './ParticipantSelectable.vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'

export default {
	name: 'ParticipantsList',

	components: {
		LoadingPlaceholder,
		Participant,
		ParticipantSelectable,
	},

	props: {
		/**
		 * List of searched users or groups
		 */
		items: {
			type: Array,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		isSearchResult: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['click'],

	computed: {
		showUserStatus() {
			return this.items.length < 100
		},

		dummyParticipants() {
			const dummies = 6 - this.items.length
			return dummies > 0 ? dummies : 0
		},
	},

	methods: {
		async handleClickParticipant(participant) {
			this.$emit('click', participant)
		},

		generateKey(participant) {
			if (participant.attendeeId) {
				// Attendee from participant list
				return 'attendee#' + participant.attendeeId
			} else if (participant.source) {
				// Search result candidate
				return 'search#' + participant.source + '#' + participant.id
			}
			return ''
		},
	},
}
</script>
