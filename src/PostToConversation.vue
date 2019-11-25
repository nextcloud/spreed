<template>
	<div>
		<Multiselect :value="currentRoom"
			:options="roomOptions"
			track-by="token"
			label="displayName"
			@input="(newValue) => newValue !== null && $emit('input', JSON.stringify({'m': currentMode.id, 't': newValue.token }))" />

		<Multiselect :value="currentMode"
			:options="modeOptions"
			track-by="id"
			label="text"
			@input="(newValue) => newValue !== null && $emit('input', JSON.stringify({'m': newValue.id, 't': currentRoom.token }))" />
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import axios from '@nextcloud/axios'

// see \OCA\Talk\Flow\Operation::MESSAGE_MODES
const conversationModeOptions = [
	{
		id: 1,
		text: t('spreed', 'Message without mention'),
	},
	{
		id: 2,
		text: t('spreed', 'Mention myself'),
	},
	{
		id: 3,
		text: t('spreed', 'Mention room'),
	},
]

export default {
	name: 'PostToConversation',
	components: { Multiselect },
	props: {
		value: {
			default: JSON.stringify({ 'm': '0', 't': '' }),
			type: String,
		},
	},
	data() {
		return {
			modeOptions: conversationModeOptions,
			roomOptions: [],
		}
	},
	computed: {
		currentRoom() {
			console.debug('room ' + this.value)
			if (this.value === '') {
				return ''
			}
			const selectedRoom = JSON.parse(this.value).t
			const newValue = this.roomOptions.find(option => option.token === selectedRoom)
			if (typeof newValue === 'undefined') {
				return ''
			}
			console.debug('sel room ' + selectedRoom)
			return newValue
		},
		currentMode() {
			console.debug('mode ' + this.value)
			if (this.value === '') {
				console.debug('def mode ' + conversationModeOptions[0].id)
				return conversationModeOptions[0].id
			}
			const selectedMode = JSON.parse(this.value).m
			const newValue = conversationModeOptions.find(option => option.id === selectedMode)
			if (typeof newValue === 'undefined') {
				console.debug('def mode2 ' + conversationModeOptions[0].id)
				return conversationModeOptions[0].id
			}
			console.debug('sel mode ' + selectedMode)
			return newValue
		},
	},
	beforeMount() {
		this.fetchRooms()
	},
	methods: {
		fetchRooms() {
			axios.get(OC.linkToOCS('/apps/spreed/api/v1', 2) + 'room').then((response) => {
				this.roomOptions = response.data.ocs.data.filter(function(room) {
					return room.readOnly === 0
				})
			})
		},
	},
}

</script>

<style scoped>
	.multiselect {
		width: 100%;
		margin: auto;
		text-align: center;
	}
</style>
