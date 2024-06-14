<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="breakout-rooms">
		<!-- Actions -->
		<BreakoutRoomsActions :main-token="mainToken"
			:main-conversation="mainConversation"
			:breakout-rooms="breakoutRooms"
			:breakout-rooms-configured="breakoutRoomsConfigured" />
		<!-- Breakout rooms list -->
		<ul v-if="showBreakoutRoomsList">
			<BreakoutRoomItem v-for="breakoutRoom in breakoutRooms"
				:key="breakoutRoom.token"
				:breakout-room="breakoutRoom"
				:main-conversation="mainConversation">
				<Participant v-for="participant in $store.getters.participantsList(breakoutRoom.token)"
					:key="participant.actorId"
					:participant="participant" />
			</BreakoutRoomItem>
		</ul>
		<NcEmptyContent v-else
			class="breakout-rooms__empty-content"
			:name="t('spreed', 'Breakout rooms are not started')">
			<template #icon>
				<DotsCircle :size="20" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'

import { t } from '@nextcloud/l10n'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import BreakoutRoomItem from './BreakoutRoomItem.vue'
import BreakoutRoomsActions from './BreakoutRoomsActions.vue'
import Participant from '../Participants/Participant.vue'

import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
import { useBreakoutRoomsStore } from '../../../stores/breakoutRooms.ts'

export default {
	name: 'BreakoutRoomsTab',

	components: {
		// Components
		BreakoutRoomItem,
		BreakoutRoomsActions,
		NcEmptyContent,
		Participant,

		// Icons
		DotsCircle,
	},

	props: {
		mainToken: {
			type: String,
			required: true,
		},

		mainConversation: {
			type: Object,
			required: true,
		},

		isActive: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		return {
			breakoutRoomsStore: useBreakoutRoomsStore(),
		}
	},

	data() {
		return {
			breakoutRoomsParticipantsInterval: undefined,
		}
	},

	computed: {
		canFullModerate() {
			return this.mainConversation.participantType === PARTICIPANT.TYPE.OWNER || this.mainConversation.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		canModerate() {
			return !this.isOneToOne && (this.canFullModerate || this.mainConversation.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		isOneToOne() {
			return this.mainConversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.mainConversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		showBreakoutRoomsList() {
			return this.breakoutRoomsConfigured
				&& (this.canModerate || this.mainConversation.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED)
		},

		breakoutRooms() {
			return this.breakoutRoomsStore.breakoutRooms(this.mainToken)
		},

		breakoutRoomsConfigured() {
			return this.mainConversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
		},
	},

	watch: {
		isActive(newValue, oldValue) {
			if (newValue) {
				// Get participants again when opening the tab
				this.getParticipants()
				// Start getting participants every 30 seconds
				this.breakoutRoomsParticipantsInterval = setInterval(() => {
					this.getParticipants()
				}, 30000)
			}

			if (oldValue) {
				// Cleanup previous intervals
				clearInterval(this.breakoutRoomsParticipantsInterval)
			}
		},
	},

	mounted() {
		// Get the breakout room every time the tab is mounted
		if (this.breakoutRoomsConfigured) {
			this.breakoutRoomsStore.getBreakoutRooms(this.mainToken)
		}
	},

	beforeDestroy() {
		// Clear the interval
		clearInterval(this.breakoutRoomsParticipantsInterval)
	},

	methods: {
		t,
		getParticipants() {
			if (this.breakoutRoomsConfigured) {
				this.breakoutRoomsStore.fetchBreakoutRoomsParticipants(this.mainToken)
			}
		},
	},
}
</script>

<style lang="scss" scoped>

.breakout-rooms {
	display: flex;
	flex-direction: column;
	height: 100%;

	&__empty-content {
		flex: 1;
	}

	&__room {
		margin-top: var(--default-grid-baseline);
	}
}

.right {
	justify-content: right;
}

:deep(.app-navigation-entry__title) {
	font-weight: bold !important;
}

// TODO: upstream collapse icon position fix
:deep(.icon-collapse) {
	position: absolute !important;
	left: 0;
}
</style>
