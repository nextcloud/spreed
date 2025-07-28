<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="transcript.length > 0"
		class="transcript">
		<p v-html="transcript" />
	</div>
</template>

<script>
export default {
	name: 'LiveTranscriptionRenderer',

	props: {
		/**
		 * The conversation token
		 */
		token: {
			type: String,
			required: true,
		},

		callParticipantModels: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			registeredModels: {},
			transcript: '',
			currentSpeaker: null,
		}
	},

	watch: {
		callParticipantModels: {
			immediate: true,
			handler(models) {
				// subscribe connected models for transcript events
				const addedModels = models.filter((model) => !this.registeredModels[model.attributes.peerId])
				addedModels.forEach((addedModel) => {
					this.registeredModels[addedModel.attributes.peerId] = addedModel
					this.registeredModels[addedModel.attributes.peerId].on('transcript', this.handleTranscript)
				})

				// unsubscribe disconnected models
				const removedModelIds = Object.keys(this.registeredModels).filter((registeredModelId) => !models.find((model) => model.attributes.peerId === registeredModelId))
				removedModelIds.forEach((removedModelId) => {
					this.registeredModels[removedModelId].off('transcript', this.handleTranscript)
					delete this.registeredModels[removedModelId]
				})
			},
		},
	},

	beforeDestroy() {
		Object.keys(this.registeredModels).forEach((modelId) => {
			this.registeredModels[modelId].off('transcript', this.handleTranscript)
			delete this.registeredModels[modelId]
		})
	},

	methods: {
		handleTranscript(model, message) {
			if (/*this.currentSpeaker &&*/ this.currentSpeaker !== model.attributes.peerId) {
				this.transcript += '<br>'
				this.currentSpeaker = model.attributes.peerId
			}

			this.transcript += message
		}
	},
}
</script>

<style lang="scss" scoped>
.transcript {
	position: absolute;
	bottom: 20px;
	left: 20%;
	right: 20%;

	line-height: 2em;
	height: 4em;
	overflow: hidden;

	display: flex;
	justify-content: center;
	align-items: center;

	z-index: 1;

	p {
		background-color: rgba(34, 34, 34, 0.8);
		color: white;

		align-self: flex-end;
	}
}
</style>
