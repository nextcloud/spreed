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
		<ul class="participants-editor__scroller">
			<BreakoutRoomItem class="participants-editor__section"
				:name="t('spreed', 'Unassigned participants')">
				<SelectableParticipant v-for="participant in unassignedParticipants"
					:key="participant.attendeeId"
					:value="participant.attendeeId"
					:checked.sync="selectedParticipants"
					:participant="participant" />
			</BreakoutRoomItem>
			<template v-for="(item, index) in assignments">
				<BreakoutRoomItem :key="index"
					class="participants-editor__section"
					:name="roomName(index)">
					<SelectableParticipant v-for="attendeeId in item"
						:key="attendeeId"
						:value="assignments"
						:checked.sync="selectedParticipants"
						:participant="attendeesById[attendeeId]" />
				</BreakoutRoomItem>
			</template>
		</ul>
		<div class="participants-editor__buttons">
			<NcButton v-if="breakoutRoomsConfigured"
				class="delete"
				:title="deleteButtonLabel"
				:aria-label="deleteButtonLabel"
				type="error"
				@click="toggleShowDialog">
				<template #icon>
					<Delete :size="20" />
				</template>
				{{ deleteButtonLabel }}
			</NcButton>
			<NcButton v-if="!isReorganizingAttendees"
				type="tertiary"
				@click="goBack">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
				{{ t('spreed', 'Back') }}
			</NcButton>
			<NcButton v-if="hasAssigned" type="tertiary" @click="resetAssignments">
				<template #icon>
					<Reload :size="20" />
				</template>
				{{ resetButtonLabel }}
			</NcButton>
			<NcActions v-if="hasSelected"
				type="primary"
				:container="container"
				:menu-name="t('spreed', 'Assign')">
				<NcActionButton v-for="(item, index) in assignments"
					:key="index"
					:close-after-click="true"
					@click="assignAttendees(index)">
					<template #icon>
						<DotsCircle :size="20" />
					</template>
					{{ roomName(index) }}
				</NcActionButton>
			</NcActions>
			<NcButton :disabled="!hasAssigned"
				:type="confirmButtonType"
				@click="handleSubmit">
				{{ confirmButtonLabel }}
			</NcButton>
		</div>
		<NcDialog :open.sync="showDialog"
			:name="t('spreed','Delete breakout rooms')"
			:message="dialogMessage"
			:container="container">
			<template #actions>
				<NcButton type="tertiary" @click="toggleShowDialog">
					{{ t('spreed', 'Cancel') }}
				</NcButton>
				<NcButton type="error" @click="deleteBreakoutRooms">
					{{ t('spreed', 'Delete breakout rooms') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import Reload from 'vue-material-design-icons/Reload.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

import BreakoutRoomItem from '../RightSidebar/BreakoutRooms/BreakoutRoomItem.vue'
import SelectableParticipant from './SelectableParticipant.vue'

import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../constants.js'

export default {
	name: 'BreakoutRoomsParticipantsEditor',

	components: {
		NcActions,
		NcActionButton,
		DotsCircle,
		Reload,
		BreakoutRoomItem,
		SelectableParticipant,
		NcButton,
		ArrowLeft,
		Delete,
		NcDialog,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		roomNumber: {
			type: Number,
			default: undefined,
		},

		breakoutRooms: {
			type: Array,
			default: undefined,
		},
	},

	emits: ['back', 'close'],

	data() {
		return {
			selectedParticipants: [],
			assignments: [],
			showDialog: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		participants() {
			return this.$store.getters.participantsList(this.token).filter(participant => {
				return (participant.participantType === PARTICIPANT.TYPE.USER
						|| participant.participantType === PARTICIPANT.TYPE.GUEST)
					&& participant.actorType === ATTENDEE.ACTOR_TYPE.USERS
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

		// If the breakoutRooms prop is populated it means that this component is
		// being used to reorganize the attendees of an existing breakout room.
		isReorganizingAttendees() {
			return this.breakoutRooms?.length
		},

		confirmButtonLabel() {
			return this.isReorganizingAttendees ? t('spreed', 'Confirm') : t('spreed', 'Create breakout rooms')
		},

		confirmButtonType() {
			return this.hasUnassigned ? 'secondary' : 'primary'
		},

		resetButtonLabel() {
			return t('spreed', 'Reset')
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		breakoutRoomsConfigured() {
			return this.conversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
		},

		deleteButtonLabel() {
			return t('spreed', 'Delete breakout rooms')
		},

		dialogMessage() {
			return t('spreed', 'Current breakout rooms and settings will be lost')
		},
	},

	created() {
		this.initialiseAssignments()
	},

	methods: {
		/**
		 * Initialise the assignments array.
		 *
		 * @param {boolean} forceReset If true, the assignments array will be reset if the breakoutRooms prop is populated.
		 */
		initialiseAssignments(forceReset) {
			if (this.isReorganizingAttendees && !forceReset) {
				this.assignments = this.breakoutRooms.map(room => {
					const participantInBreakoutRoomActorIdList = this.$store.getters.participantsList(room.token)
						.map(participant => participant.actorId)

					return this.participants.filter(participant => {
						return participantInBreakoutRoomActorIdList.includes(participant.actorId)
					}).map(participant => participant.attendeeId)
				})
			} else {
				this.assignments = Array.from(Array(this.isReorganizingAttendees
					? this.breakoutRooms.length
					: this.roomNumber), () => [])
			}
		},

		assignAttendees(roomIndex) {
			this.selectedParticipants.forEach(attendeeId => {
				if (this.unassignedParticipants.find(participant => participant.attendeeId === attendeeId)) {
					this.assignments[roomIndex].push(attendeeId)
					return
				}

				const assignedRoomIndex = this.assignments.findIndex(room => room.includes(attendeeId))

				if (assignedRoomIndex === roomIndex) {
					return
				}

				this.assignments[assignedRoomIndex].splice(this.assignments[assignedRoomIndex].findIndex(id => id === attendeeId), 1)
				this.assignments[roomIndex].push(attendeeId)
			})

			this.selectedParticipants = []
		},

		roomName(index) {
			const roomNumber = index + 1
			return t('spreed', 'Room {roomNumber}', { roomNumber })
		},

		resetAssignments() {
			this.selectedParticipants = []
			this.assignments = []
			this.initialiseAssignments(true)
		},

		goBack() {
			this.$emit('back')
		},

		handleSubmit() {
			this.isReorganizingAttendees ? this.reorganizeAttendees() : this.createRooms()
		},

		createAttendeeMap() {
			const attendeeMap = {}
			this.assignments.forEach((room, index) => {
				room.forEach(attendeeId => {
					attendeeMap[attendeeId] = index
				})
			})
			return JSON.stringify(attendeeMap)
		},

		createRooms() {
			this.$store.dispatch('configureBreakoutRoomsAction', {
				token: this.token,
				mode: 2,
				amount: this.roomNumber,
				attendeeMap: this.createAttendeeMap(),
			})
			this.$emit('close')
		},

		reorganizeAttendees() {
			this.$store.dispatch('reorganizeAttendeesAction', {
				token: this.token,
				attendeeMap: this.createAttendeeMap(),
			})
			this.$emit('close')
		},

		toggleShowDialog() {
			this.showDialog = !this.showDialog
		},

		deleteBreakoutRooms() {
			this.$store.dispatch('deleteBreakoutRoomsAction', {
				token: this.token,
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
	height: calc(100% - 42px);

	&__section {
		margin: calc(var(--default-grid-baseline) * 2) 0 var(--default-grid-baseline) 0;

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

// TODO: upstream collapse icon position fix
:deep(.icon-collapse) {
	position: absolute !important;
	left: 0;
}

:deep(.dialog) {
	padding-block: 0px 8px;
	padding-inline: 12px 8px;
}

.delete {
	margin-right: auto;
}
</style>
