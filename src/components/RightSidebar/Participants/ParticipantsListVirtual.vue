<!--
  - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Grigorii Shartsev <me@shgk.me>
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
