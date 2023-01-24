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
		<div class="participants-editor__scroller">
			<NcAppNavigationItem v-if="hasUnassigned"
				key="unassigned"
				class="participants-editor__section"
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
					class="participants-editor__section"
					:title="roomName(index)"
					:allow-collapse="true"
					:open="true">
					<template #icon>
						<GoogleCircles :size="20" />
					</template>
					<SelectableParticipant v-for="attendeeId in item"
						:key="attendeeId"
						:value="assignments"
						:checked.sync="selectedParticipants"
						:participant="attendeesById[attendeeId]" />
				</NcAppNavigationItem>
			</template>
		</div>
		<div class="participants-editor__buttons">
			<NcButton type="tertiary" @click="goBack">
				<template #icon>
					<!-- TODO: choose final icon -->
					<ArrowLeft :size="20" />
				</template>
				{{ t('spreed', 'Back') }}
			</NcButton>
			<NcButton v-if="hasAssigned" type="tertiary" @click="resetAssignments">
				<template #icon>
					<Reload :size="20" />
				</template>
				{{ t('spreed', 'Reset') }}
			</NcButton>
			<NcActions v-if="hasSelected"
				:menu-title="t('spreed', 'Assign participants')">
				<NcActionButton v-for="(item, index) in assignments"
					:key="index"
					:close-after-click="true"
					@click="assignAttendees(index)">
					<template #icon>
						<!-- TODO: choose final icon -->
						<GoogleCircles :size="20" />
					</template>
					{{ roomName(index) }}
				</NcActionButton>
			</NcActions>
			<NcButton type="primary" @click="handleCreateRooms">
				{{ t('spreed', 'Create breakout rooms') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import SelectableParticipant from './SelectableParticipant.vue'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import { PARTICIPANT } from '../../constants.js'

export default {
	name: 'BreakoutRoomsParticipantsEditor',

	components: {
		NcActions,
		NcActionButton,
		GoogleCircles,
		Reload,
		NcAppNavigationItem,
		SelectableParticipant,
		NcButton,
		ArrowLeft,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		roomNumber: {
			type: Number,
			required: true,
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
			return this.$store.getters.participantsList(this.token).filter(participant => {
				return participant.participantType === PARTICIPANT.TYPE.USER
					|| participant.participantType === PARTICIPANT.TYPE.GUEST
			})
		},

		attendeesById() {
			return this.$store.state.participantsStore.attendees[this.token]
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
			return this.selectedParticipants.length > 0
		},

		hasAssigned() {
			return this.assignments.flat().length > 0
		},

		// True if there's one or more unassigned participants
		hasUnassigned() {
			return this.unassignedParticipants.length > 0
		},
	},

	created() {
		this.initialiseAssignments()
	},

	methods: {
		initialiseAssignments() {
			this.assignments = Array.from(Array(this.roomNumber), () => [])
		},

		assignAttendees(roomIndex) {
			this.assignments[roomIndex].push(...this.selectedParticipants)
			this.selectedParticipants = []
		},

		roomName(index) {
			const roomNumber = index + 1
			return t('spreed', 'Room {roomNumber}', { roomNumber })
		},

		resetAssignments() {
			this.selectedParticipants = []
			this.assignments = []
			this.initialiseAssignments()
		},

		goBack() {
			this.$emit('back')
		},

		handleCreateRooms() {
			let attendeeMap = {}
			this.assignments.forEach((room, index) => {
				room.forEach(attendeeId => {
					attendeeMap[attendeeId] = index
				})
			})
			attendeeMap = JSON.stringify(attendeeMap)
			this.$store.dispatch('configureBreakoutRoomsAction', {
				token: this.token,
				mode: 2,
				amount: this.roomNumber,
				attendeeMap,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.participants-editor {
	display: flex;
	width: 100%;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	height: 100%;

	&__section {
		margin: calc(var(--default-grid-baseline) * 2) 0 var(--default-grid-baseline) 0;

	}

	&__participant {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		margin-left: 14px;
	}

	&__scroller {
		height: 100%;
		overflow: auto;
	}

	&__buttons {
		display: flex;
		justify-content: flex-end;
		gap: calc(var(--default-grid-baseline) * 2);
	}
}

// TODO: upsteream collapse icon position fix
::v-deep .icon-collapse {
	position: absolute !important;
	left: 0;
}
</style>
