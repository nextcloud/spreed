<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="participants-editor">
		<ul class="participants-editor__scroller">
			<BreakoutRoomItem key="unassigned"
				class="participants-editor__section"
				:name="t('spreed', 'Unassigned participants')">
				<SelectableParticipant v-for="participant in unassignedParticipants"
					:key="participant.attendeeId"
					v-model:checked="selectedParticipants"
					:value="participant.attendeeId"
					:participant="participant" />
			</BreakoutRoomItem>
			<BreakoutRoomItem v-for="(item, index) in assignments"
				:key="index"
				class="participants-editor__section"
				:name="roomName(index)">
				<SelectableParticipant v-for="attendeeId in item"
					:key="attendeeId"
					v-model:checked="selectedParticipants"
					:value="assignments"
					:participant="attendeesById[attendeeId]" />
			</BreakoutRoomItem>
		</ul>
		<div class="participants-editor__buttons">
			<NcButton v-if="breakoutRoomsConfigured"
				class="delete"
				:title="deleteButtonLabel"
				:aria-label="deleteButtonLabel"
				variant="error"
				@click="toggleShowDialog">
				<template #icon>
					<IconDeleteOutline :size="20" />
				</template>
				{{ deleteButtonLabel }}
			</NcButton>
			<NcButton v-if="!isReorganizingAttendees"
				variant="tertiary"
				@click="goBack">
				<template #icon>
					<IconArrowLeft class="bidirectional-icon" :size="20" />
				</template>
				{{ t('spreed', 'Back') }}
			</NcButton>
			<NcButton v-if="hasAssigned" variant="tertiary" @click="resetAssignments">
				<template #icon>
					<Reload :size="20" />
				</template>
				{{ resetButtonLabel }}
			</NcButton>
			<NcActions v-if="hasSelected"
				variant="primary"
				container=".participants-editor__buttons"
				:menu-name="t('spreed', 'Assign')">
				<NcActionButton v-for="(item, index) in assignments"
					:key="index"
					close-after-click
					@click="assignAttendees(index)">
					<template #icon>
						<DotsCircle :size="20" />
					</template>
					{{ roomName(index) }}
				</NcActionButton>
			</NcActions>
			<NcButton :disabled="!hasAssigned"
				:variant="hasUnassigned ? 'secondary' : 'primary'"
				@click="handleSubmit">
				{{ confirmButtonLabel }}
			</NcButton>
		</div>
		<NcDialog v-if="showDialog"
			v-model:open="showDialog"
			:name="t('spreed', 'Delete breakout rooms')"
			:message="dialogMessage"
			container=".participants-editor">
			<template #actions>
				<NcButton variant="tertiary" @click="toggleShowDialog">
					{{ t('spreed', 'Cancel') }}
				</NcButton>
				<NcButton variant="error" @click="deleteBreakoutRooms">
					{{ t('spreed', 'Delete breakout rooms') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { provide } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconDeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import BreakoutRoomItem from '../RightSidebar/BreakoutRooms/BreakoutRoomItem.vue'
import SelectableParticipant from './SelectableParticipant.vue'
import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'

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
		IconArrowLeft,
		IconDeleteOutline,
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
			default: () => [],
		},
	},

	emits: ['back', 'close'],

	setup() {
		// Add a visual bulk selection state for SelectableParticipant component
		provide('bulkParticipantsSelection', true)

		return {
			breakoutRoomsStore: useBreakoutRoomsStore(),
		}
	},

	data() {
		return {
			selectedParticipants: [],
			assignments: [],
			showDialog: false,
		}
	},

	computed: {
		participants() {
			return this.$store.getters.participantsList(this.token).filter((participant) => {
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
			return this.participants.filter((participant) => {
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
			return this.breakoutRooms.length
		},

		confirmButtonLabel() {
			return this.isReorganizingAttendees ? t('spreed', 'Confirm') : t('spreed', 'Create breakout rooms')
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
		t,
		/**
		 * Initialise the assignments array.
		 *
		 * @param {boolean} forceReset If true, the assignments array will be reset if the breakoutRooms prop is populated.
		 */
		initialiseAssignments(forceReset) {
			if (this.isReorganizingAttendees && !forceReset) {
				this.assignments = this.breakoutRooms.map((room) => {
					const participantInBreakoutRoomActorIdList = this.$store.getters.participantsList(room.token)
						.map((participant) => participant.actorId)

					return this.participants.filter((participant) => {
						return participantInBreakoutRoomActorIdList.includes(participant.actorId)
					}).map((participant) => participant.attendeeId)
				})
			} else {
				this.assignments = Array.from(Array(this.isReorganizingAttendees
					? this.breakoutRooms.length
					: this.roomNumber), () => [])
			}
		},

		assignAttendees(roomIndex) {
			this.selectedParticipants.forEach((attendeeId) => {
				if (this.unassignedParticipants.find((participant) => participant.attendeeId === attendeeId)) {
					this.assignments[roomIndex].push(attendeeId)
					return
				}

				const assignedRoomIndex = this.assignments.findIndex((room) => room.includes(attendeeId))

				if (assignedRoomIndex === roomIndex) {
					return
				}

				this.assignments[assignedRoomIndex].splice(this.assignments[assignedRoomIndex].findIndex((id) => id === attendeeId), 1)
				this.assignments[roomIndex].push(attendeeId)
			})

			this.selectedParticipants = []
		},

		roomName(index) {
			return this.breakoutRooms[index]?.displayName
				?? t('spreed', 'Room {roomNumber}', { roomNumber: index + 1 })
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
				room.forEach((attendeeId) => {
					attendeeMap[attendeeId] = index
				})
			})
			return JSON.stringify(attendeeMap)
		},

		createRooms() {
			this.breakoutRoomsStore.configureBreakoutRooms({
				token: this.token,
				mode: 2,
				amount: this.roomNumber,
				attendeeMap: this.createAttendeeMap(),
			})
			this.$emit('close')
		},

		reorganizeAttendees() {
			this.breakoutRoomsStore.reorganizeAttendees({
				token: this.token,
				attendeeMap: this.createAttendeeMap(),
			})
			this.$emit('close')
		},

		toggleShowDialog() {
			this.showDialog = !this.showDialog
		},

		deleteBreakoutRooms() {
			this.breakoutRoomsStore.deleteBreakoutRooms(this.token)
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
	height: calc(100% - 57px); // heading 30px * 1.5 line-height + 12px margin-bottom

	&__section {
		margin: calc(var(--default-grid-baseline) * 2) 0 calc(var(--default-grid-baseline) * 4);

	}

	&__scroller {
		height: 100%;
		overflow: auto;
	}

	&__buttons {
		display: flex;
		justify-content: flex-end;
		gap: calc(var(--default-grid-baseline) * 2);
		padding-top: 10px;
	}
}

// Warning dialog when deleting breakout rooms
:deep(.dialog) {
	padding-block: 0px 8px;
	padding-inline: 12px 8px;
}

.delete {
	margin-inline-end: auto;
}
</style>
