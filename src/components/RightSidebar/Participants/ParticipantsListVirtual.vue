<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<RecycleScroller ref="scroller"
		list-tag="ul"
		item-tag="li"
		:items="participants"
		:item-size="PARTICIPANT_ITEM_SIZE"
		key-field="attendeeId">
		<template #default="{ item }">
			<Participant :participant="item" tag="div" />
		</template>
		<template v-if="loading" #after>
			<LoadingPlaceholder type="participants" :count="dummyParticipants" />
		</template>
	</RecycleScroller>
</template>

<script>
import { RecycleScroller } from 'vue-virtual-scroller'

import Participant from './Participant.vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

const PARTICIPANT_ITEM_SIZE = 64

export default {
	name: 'ParticipantsListVirtual',

	components: {
		LoadingPlaceholder,
		Participant,
		RecycleScroller,
	},

	props: {
		participants: {
			type: Array,
			required: true,
		},

		loading: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return {
			PARTICIPANT_ITEM_SIZE,
		}
	},

	computed: {
		dummyParticipants() {
			const dummies = 6 - this.participants.length
			return dummies > 0 ? dummies : 0
		},
	},
}
</script>
