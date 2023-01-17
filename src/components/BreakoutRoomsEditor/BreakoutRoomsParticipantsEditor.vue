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
			<NcActionButton v-for="(item, index) in configuration" :key="index" @click="assignAttendees(index)">
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
			<div v-for="participant in participants"
				:key="participant.attendeeId"
				tabindex="0"
				class="participants-editor__participant">
				<input id="participant.attendeeId"
					v-model="selectedParticipants"
					:value="participant.attendeeId"
					type="checkbox"
					name="participant.attendeeId">
				<!-- Participant's avatar -->
				<AvatarWrapper :id="participant.id"
					:disable-tooltip="true"
					:disable-menu="true"
					:size="44"
					:show-user-status="true"
					:name="participant.displayName"
					:source="participant.source || participant.actorType" />
				<div>
					{{ participant.displayName }}
				</div>
			</div>
			<template #icon>
				<GoogleCircles :size="20" />
			</template>
		</NcAppNavigationItem>
		<template v-for="(item, index) in configuration">
			<NcAppNavigationItem :key="index"
				:title="roomName(index)"
				:allow-collapse="true"
				:open="true">
				<template #icon>
					<GoogleCircles :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
	</div>
</template>

<script>
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

export default {
	name: 'BreakoutRoomsParticipantsEditor',

	components: {
		AvatarWrapper,
		NcActions,
		NcActionButton,
		GoogleCircles,
		Reload,
		NcAppNavigationItem,
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
			configuration: [],
		}
	},

	computed: {
		participants() {
			return this.$store.getters.participantsList(this.token)
		},

		hasSelected() {
			return this.selectedParticipants.length !== 0
		},
	},

	created() {
		this.initialiseConfiguration()
	},

	methods: {
		initialiseConfiguration() {
			let count = 0
			while (count < this.roomNumber) {
				this.configuration.push([])
				count++
			}
		},

		assignAttendees(roomIndex) {
			debugger
			this.configuration[roomIndex].push(...this.selectedParticipants)
			this.selectedParticipants = []
		},

		roomName(index) {
			const roomNumber = index + 1
			return t('spreed', 'Room {roomNumber}', { roomNumber })
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
