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
	<div class="breakout-rooms">
		<!-- Configuration button -->
		<NcButton v-if="!breakoutRoomsConfigured"
			type="secondary"
			@click="openBreakoutRoomsEditor">
			<template #icon>
				<DotsCircle :size="20" />
			</template>
			{{ t('spreed', 'Setup breakout rooms for this conversation') }}
		</NcButton>
		<template v-if="breakoutRoomsConfigured">
			<NcButton v-tooltip.auto="t('spreed', 'Delete breakout rooms')"
				type="tertiary-no-background"
				@click="deleteBreakoutRooms">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
			<div v-if="false">
				<template v-for="breakoutRoom in breakoutRooms">
					<NcAppNavigationItem :key="breakoutRoom.displayName"
						:title="breakoutRoom.displayName"
						:allow-collapse="true"
						:open="true">
						<template #icon>
							<!-- TODO: choose final icon -->
							<GoogleCircles :size="20" />
						</template>
						<template v-for="participant in $store.getters.participantsList(breakoutRoom.token)">
							<Participant :key="participant.actorId" :participant="participant" />
						</template>
					</NcAppNavigationItem>
				</template>
			</div>
		</template>

		<!-- Breakout rooms editor -->
		<BreakoutRoomsEditor v-if="showBreakoutRoomsEditor"
			:token="token"
			@close="showBreakoutRoomsEditor = false" />
	</div>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import Participant from '../Participants/ParticipantsList/Participant/Participant.vue'
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { CONVERSATION } from '../../../constants.js'
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import BreakoutRoomsEditor from '../../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'

export default {
	name: 'BreakoutRoomsTab',

	components: {
		NcAppNavigationItem,
		Participant,
		GoogleCircles,
		Delete,
		NcButton,
		DotsCircle,
		BreakoutRoomsEditor,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		conversation: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			showBreakoutRoomsEditor: false,
		}
	},

	computed: {
		// TODO: get actual rooms
		breakoutRooms() {
			return this.$store.getters.breakoutRoomsReferences(this.token).map(reference => {
				return this.$store.getters.conversation(reference)
			})
		},

		breakoutRoomsConfigured() {
			return this.conversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
		},
	},

	mounted() {
		/**
		if (this.breakoutRoomsConfigured) {
			this.$store.dispatch('getBreakoutRoomsAction', {
				token: this.token,
			})
		}
		 */
	},

	methods: {
		deleteBreakoutRooms() {
			OC.dialogs.confirmDestructive(
				t('spreed', 'Current breakout rooms settings and configuration will be lost'),
				t('spreed', 'Delete breakout rooms'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('spreed', 'Delete breakout rooms'),
					confirmClasses: 'error',
					cancel: t('spreed', 'Cancel'),
				},
				(decision) => {
					if (!decision) {
						return
					}
					this.$store.dispatch('deleteBreakoutRoomsAction', {
						token: this.token,
					})
				}
			)
		},

		openBreakoutRoomsEditor() {
			this.showBreakoutRoomsEditor = true
		},
	},
}
</script>

<style lang="scss" scoped>

::v-deep .app-navigation-entry__title {
	font-weight: bold !important;
}

// TODO: upsteream collapse icon position fix
::v-deep .icon-collapse {
	position: absolute !important;
	left: 0;
}
</style>
