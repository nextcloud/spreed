<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul>
		<Participant v-for="item in items"
			:key="generateKey(item)"
			:participant="item"
			:show-user-status="showUserStatus"
			@click-participant="handleClickParticipant" />
		<LoadingPlaceholder v-if="loading" type="participants" :count="dummyParticipants" />
	</ul>
</template>

<script>

import Participant from './Participant.vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'

export default {
	name: 'ParticipantsList',

	components: {
		LoadingPlaceholder,
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
		loading: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['click'],

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

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
			let key = ''
			if (participant.attendeeId) {
				// Attendee from participant list
				key = 'attendee#' + participant.attendeeId
			} else if (participant.source) {
				// Search result candidate
				key = 'search#' + participant.source + '#' + participant.id
			}
			return key
		},
	},
}
</script>
