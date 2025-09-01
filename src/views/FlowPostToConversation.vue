<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcSelect
			:model-value="currentRoom"
			:options="roomOptions"
			:aria-label-combobox="t('spreed', 'Select a conversation')"
			label="displayName"
			@update:model-value="newValue => emitEvents(currentMode.id, newValue.token)" />

		<NcSelect
			:model-value="currentMode"
			:options="modeOptions"
			:aria-label-combobox="t('spreed', 'Select a mode')"
			label="text"
			@update:model-value="newValue => emitEvents(newValue.id, currentRoom.token)" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { CONVERSATION, FLOW, PARTICIPANT } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'

const supportFederationV1 = hasTalkFeature('local', 'federation-v1')

export default {
	name: 'FlowPostToConversation',
	components: { NcSelect },
	props: {
		modelValue: {
			default: '',
			type: String,
		},

		// keep "value" for backwards compatibility, up to NC 31
		value: {
			default: JSON.stringify({ m: '0', t: '' }),
			type: String,
		},
	},

	emits: ['input', 'update:modelValue'],

	data() {
		return {
			modeOptions: [
				{
					id: FLOW.MESSAGE_MODES.NO_MENTION,
					text: t('spreed', 'Message without mention'),
				},
				{
					id: FLOW.MESSAGE_MODES.SELF_MENTION,
					text: t('spreed', 'Mention myself'),
				},
				{
					id: FLOW.MESSAGE_MODES.ROOM_MENTION,
					text: t('spreed', 'Mention everyone'),
				},
			],

			roomOptions: [],
		}
	},

	computed: {
		currentRoom() {
			if (this.modelValue === '' && this.value === '') {
				return ''
			}
			const selectedRoom = JSON.parse(this.modelValue || this.value).t
			const newValue = this.roomOptions.find((option) => option.token === selectedRoom)
			if (typeof newValue === 'undefined') {
				return ''
			}
			return newValue
		},

		currentMode() {
			if (this.modelValue === '' && this.value === '') {
				return this.modeOptions[0]
			}
			const selectedMode = JSON.parse(this.modelValue || this.value).m
			const newValue = this.modeOptions.find((option) => option.id === selectedMode)
			if (typeof newValue === 'undefined') {
				return this.modeOptions[0]
			}
			return newValue
		},
	},

	beforeMount() {
		this.fetchRooms()
	},

	methods: {
		t,
		fetchRooms() {
			axios.get(generateOcsUrl('/apps/spreed/api/v4/room')).then((response) => {
				this.roomOptions = response.data.ocs.data.filter(function(room) {
					return room.readOnly === CONVERSATION.STATE.READ_WRITE
						&& (room.permissions & PARTICIPANT.PERMISSIONS.CHAT) !== 0
						&& (!supportFederationV1 || !room.remoteServer)
				})
			})
		},

		emitEvents(mode, token) {
			if (mode === null || token === null) {
				return
			}
			this.$emit('input', JSON.stringify({ m: mode, t: token }))
			this.$emit('update:modelValue', JSON.stringify({ m: mode, t: token }))
		},
	},
}

</script>

<style scoped>
	:deep(.v-select) {
		width: 100%;
		margin: auto;
		text-align: center;
	}
</style>
