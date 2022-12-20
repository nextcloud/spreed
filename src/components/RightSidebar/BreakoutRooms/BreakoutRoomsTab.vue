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
		<div class="breakout-rooms__actions">
			<template v-if="breakoutRoomsConfigured">
				<NcButton v-if="breakoutRoomsStarted"
					v-tooltip.auto="t('spreed', 'Start breakout rooms')"
					type="secondary"
					@click="startBreakoutRooms">
					<template #icon>
						<Play :size="20" />
					</template>
				</NcButton>
				<NcButton v-else
					v-tooltip.auto="t('spreed', 'Start breakout rooms')"
					type="secondary"
					@click="stopBreakoutRooms">
					<template #icon>
						<StopIcon :size="20" />
					</template>
				</NcButton>
			</template>

			<!-- Configuration button -->
			<NcButton :wide="true"
				:type="breakoutRoomsConfigured ? 'tertiary' : 'secondary'"
				@click="openBreakoutRoomsEditor">
				<template #icon>
					<DotsCircle :size="20" />
				</template>
				<template v-if="!breakoutRoomsConfigured">
					{{ t('spreed', 'Configure breakout rooms') }}
				</template>
				<template v-else>
					{{ t('spreed', 'Re-configure breakout rooms') }}
				</template>
			</NcButton>
			<NcButton v-if="breakoutRoomsConfigured"
				v-tooltip.auto="t('spreed', 'Delete breakout rooms')"
				type="tertiary-no-background"
				@click="deleteBreakoutRooms">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
		</div>
		<template v-if="breakoutRoomsConfigured">
			<template v-if="breakoutRooms">
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
			</template>
		</template>

		<!-- Breakout rooms editor -->
		<BreakoutRoomsEditor v-if="showBreakoutRoomsEditor"
			:token="token"
			@close="showBreakoutRoomsEditor = false" />
	</div>
</template>

<script>
// Components
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import Participant from '../Participants/ParticipantsList/Participant/Participant.vue'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import BreakoutRoomsEditor from '../../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'

// Icons
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Play from 'vue-material-design-icons/Play.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'

// Constants
import { CONVERSATION } from '../../../constants.js'

export default {
	name: 'BreakoutRoomsTab',

	components: {
		// Components
		NcAppNavigationItem,
		Participant,
		NcButton,
		BreakoutRoomsEditor,

		// Icons
		GoogleCircles,
		Delete,
		Play,
		DotsCircle,
		StopIcon,
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
		breakoutRooms() {
			return this.$store.getters.breakoutRoomsReferences(this.token).map(reference => {
				return this.$store.getters.conversation(reference)
			})
		},

		breakoutRoomsConfigured() {
			return this.conversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
		},

		breakoutRoomsStarted() {
			return this.conversation.breakoutRoomStatus !== CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED
		},
	},

	mounted() {
		if (this.breakoutRoomsConfigured) {
			this.$store.dispatch('getBreakoutRoomsAction', {
				token: this.token,
			})
		}
	},

	methods: {
		deleteBreakoutRooms() {
			OC.dialogs.confirmDestructive(
				t('spreed', 'Current breakout rooms and settings will be lost'),
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
			if (!this.breakoutRoomsConfigured) {
				this.showBreakoutRoomsEditor = true
				return
			}
			OC.dialogs.confirmDestructive(
				t('spreed', 'Current breakout rooms and settings will be lost'),
				t('spreed', 'Re-configure breakout rooms'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('spreed', 'Re-configure breakout rooms'),
					confirmClasses: 'primary',
					cancel: t('spreed', 'Cancel'),
				},
				(decision) => {
					if (!decision) {
						return
					}
					this.showBreakoutRoomsEditor = true
				}
			)
		},

		startBreakoutRooms() {
			this.$store.dispatch('startBreakoutRoomsAction', this.token)
		},

		stopBreakoutRooms() {
			this.$store.dispatch('stopBreakoutRoomsAction', this.token)
		},
	},
}
</script>

<style lang="scss" scoped>

.breakout-rooms {
	&__actions {
		display: flex;
		justify-content: flex-end;
	}
}

::v-deep .app-navigation-entry__title {
	font-weight: bold !important;
}

// TODO: upsteream collapse icon position fix
::v-deep .icon-collapse {
	position: absolute !important;
	left: 0;
}
</style>
