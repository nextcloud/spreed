<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="participants-editor">
		<NcActions v-if="hasSelected" :menu-title="t('spreed', 'Assign participants to room')">
			<NcActionButton v-for="(item, index) in assignments" :key="index" @click="assignAttendees(index)">
				<template #icon>
					<!-- TODO: choose final icon -->
					<GoogleCircles :size="20" />
				</template>
				{{ roomName(index) }}
			</NcActionButton>
			<NcActionButton>
				<template #icon>
					<Reload :size="20" />
				</template>
				{{ t('spreed', 'Reset all assignments') }}
			</NcActionButton>
		</NcActions>
		<NcAppNavigationItem key="unassigned"
			:title="t('spreed', 'Unassigned participants')"
			:allow-collapse="true"
			:open="true">
			<template #icon>
				<GoogleCircles :size="20" />
			</template>
			<SelectableParticipant v-for="participant in unassignedParticipants"
				:key="participant.attendeeId"
				:value="participant.attendeeId"
				:checked.sync="selectedParticipants"
				:participant="participant" />
		</NcAppNavigationItem>
		<template v-for="(item, index) in assignments">
			<NcAppNavigationItem :key="index"
				:title="roomName(index)"
				:allow-collapse="true"
				:open="true">
				<template #icon>
					<GoogleCircles :size="20" />
				</template>
				<SelectableParticipant v-for="participant in filteredParticipants(index)"
					:key="participant.attendeeId"
					:value="assignments"
					:checked.sync="selectedParticipants"
					:participant="participant" />
			</NcAppNavigationItem>
		</template>
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import SelectableParticipant from './SelectableParticipant.vue'

export default {
	name: 'BreakoutRoomsParticipantsEditor',

	components: {
		NcActions,
		NcActionButton,
		GoogleCircles,
		Reload,
		NcAppNavigationItem,
		SelectableParticipant,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		roomNumber: {
			type: Number,
			default: 3,
		},
	},

	data() {
		return {
			selectedParticipants: [],
			assignments: [],
		}
	},

	computed: {
		participants() {
			return this.$store.getters.participantsList(this.token)
		},

		unassignedParticipants() {
			if (this.assignments.length === 0) {
				return []
			}
			// Flatten assignments array
			const assignedParticipants = this.assignments.flat()
			return this.participants.filter(participant => {
				return !assignedParticipants.includes(participant.attendeeId)
			})
		},

		hasSelected() {
			return this.selectedParticipants.length !== 0
		},

	},

	created() {
		this.initialiseAssignments()
	},

	methods: {
		initialiseAssignments() {
			let count = 0
			while (count < this.roomNumber) {
				this.assignments.push([])
				count++
			}
		},

		assignAttendees(roomIndex) {
			this.assignments[roomIndex].push(...this.selectedParticipants)
			this.selectedParticipants = []
		},

		roomName(index) {
			const roomNumber = index + 1
			return t('spreed', 'Room {roomNumber}', { roomNumber })
		},

		filteredParticipants(index) {
			return this.participants.filter(participant => {
				return this.assignments[index].includes(participant.attendeeId)
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.participants-editor {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	&__participant {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		margin-left: 14px;
	}
}
</style>
