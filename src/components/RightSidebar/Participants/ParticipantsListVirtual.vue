<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<RecycleScroller ref="scroller"
		item-tag="ul"
		:items="participants"
		:item-size="PARTICIPANT_ITEM_SIZE"
		key-field="attendeeId">
		<template #default="{ item }">
			<Participant :participant="item" />
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

import { AVATAR } from '../../../constants.js'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

/* Consider:
 * avatar size (and two lines of text)
 * list-item padding
 * list-item__wrapper padding
 */
const PARTICIPANT_ITEM_SIZE = AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2

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
