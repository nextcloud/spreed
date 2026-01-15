<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Fragment>
		<div class="breakout-rooms-settings">
			<p class="breakout-rooms-settings__hint">
				{{ hintText }}
			</p>
			<NcButton
				v-if="hasBreakoutRooms"
				variant="secondary"
				@click="showParticipantsEditor = true">
				<template #icon>
					<DotsCircle :size="20" />
				</template>
				{{ t('spreed', 'Manage breakout rooms') }}
			</NcButton>
			<NcButton
				v-else
				type="secondary"
				@click="openBreakoutRoomsEditor">
				<template #icon>
					<DotsCircle :size="20" />
				</template>
				{{ t('spreed', 'Set up breakout rooms for this conversation') }}
			</NcButton>
		</div>
		<!-- Breakout rooms editor -->
		<BreakoutRoomsEditor v-if="showBreakoutRoomsEditor"
			:token="token"
			@close="showBreakoutRoomsEditor = false" />

		<!-- Participants editor -->
		<NcModal
			v-if="showParticipantsEditor"
			container=".breakout-rooms-settings"
			label-id="breakout-rooms-settings-editor"
			@close="showParticipantsEditor = false">
			<div class="breakout-rooms-settings__editor">
				<h2 id="breakout-rooms-settings-editor" class="nc-dialog-alike-header">
					{{ t('spreed', 'Manage breakout rooms') }}
				</h2>
				<BreakoutRoomsParticipantsEditor
					:token="token"
					:breakout-rooms="breakoutRooms"
					@close="showParticipantsEditor = false" />
			</div>
		</NcModal>
	</Fragment>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { Fragment } from 'vue-frag'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import BreakoutRoomsEditor from '../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'
import BreakoutRoomsParticipantsEditor from '../BreakoutRoomsEditor/BreakoutRoomsParticipantsEditor.vue'
import { CONVERSATION } from '../../constants.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'

export default {
	name: 'BreakoutRoomsSettings',

	components: {
		NcButton,
		NcModal,
		BreakoutRoomsEditor,
		BreakoutRoomsParticipantsEditor,
		Fragment,
		DotsCircle,
	},

	props: {
		/**
		 * The conversation's token
		 */
		token: {
			type: String,
			required: true,
		},
	},

	setup() {
		const breakoutRoomsStore = useBreakoutRoomsStore()

		return {
			breakoutRoomsStore,
		}
	},

	data() {
		return {
			showBreakoutRoomsEditor: false,
			showParticipantsEditor: false,
		}
	},

	computed: {
		hintText() {
			return t('spreed', 'Breakout rooms') // FIXME
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		hasBreakoutRooms() {
			return this.conversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
		},

		breakoutRooms() {
			return this.breakoutRoomsStore.breakoutRooms(this.token)
		},
	},

	created() {
		if (this.hasBreakoutRooms) {
			this.breakoutRoomsStore.fetchBreakoutRoomsParticipants(this.token)
		}
	},

	methods: {
		t,
		openBreakoutRoomsEditor() {
			this.showBreakoutRoomsEditor = true
		},
	},
}
</script>

<style lang="scss" scoped>
.breakout-rooms-settings {
	&__hint {
		margin-bottom: calc(var(--default-grid-baseline) * 2);
		color: var(--color-text-maxcontrast);
	}

	&__editor {
		height: 100%;
		padding: 20px;
	}
}

</style>
