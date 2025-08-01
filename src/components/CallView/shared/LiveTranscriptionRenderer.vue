<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="transcript">
		<p ref="transcriptParagraph" />
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
			currentSpeaker: null,
			transcripts: [],
		}
	},

	mounted() {
		window.transcriptionRenderer = this
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
			if (this.currentSpeaker && this.currentSpeaker !== model.attributes.peerId) {
				const lineBreak = document.createElement('br')
				this.transcripts.push(lineBreak)

				this.$refs.transcriptParagraph.append(lineBreak)
			}

			this.currentSpeaker = model.attributes.peerId

			const transcriptSpan = document.createElement('span')
			transcriptSpan.textContent = message
			this.transcripts.push(transcriptSpan)

			this.$refs.transcriptParagraph.append(transcriptSpan)

			this.$nextTick(() => {
				while (this.removeFirstNoLongerVisibleLine()) {
				}
			})
		},

		removeFirstNoLongerVisibleLine() {
			if (this.transcripts.length === 0) {
				return false
			}

			const firstLineTop = this.transcripts[0].getClientRects()[0].top

			const transcriptsInFirstLine = []
			let transcriptsInFirstLineBottom = 0
			for (let transcript of this.transcripts) {
				const transcriptFirstLineClientRect = transcript.getClientRects()[0]
				if (transcriptFirstLineClientRect.top > firstLineTop) {
					break;
				}

				transcriptsInFirstLine.push(transcript)
				transcriptsInFirstLineBottom = Math.max(transcriptsInFirstLineBottom, transcriptFirstLineClientRect.bottom)
			}

			const paragraphWrapperTop = this.$refs.transcriptParagraph.parentElement.getBoundingClientRect().top

			if (transcriptsInFirstLineBottom > paragraphWrapperTop) {
				return false
			}

			const lastTranscriptInFirstLine = transcriptsInFirstLine.at(-1)
			let lastClientRectInLastTranscriptInFirstLine = lastTranscriptInFirstLine.getClientRects()[lastTranscriptInFirstLine.getClientRects().length - 1]

			if (lastClientRectInLastTranscriptInFirstLine.bottom > paragraphWrapperTop) {
				return false
			}

			const replaceLastTranscript = lastTranscriptInFirstLine.getClientRects().length > 1
			const placeholderWidth = lastClientRectInLastTranscriptInFirstLine.width
			const placeholderHeight = lastClientRectInLastTranscriptInFirstLine.height

			for (let transcript of transcriptsInFirstLine) {
				this.$refs.transcriptParagraph.removeChild(transcript)
			}
			this.transcripts.splice(0, transcriptsInFirstLine.length)

			if (replaceLastTranscript) {
				const placeholder = document.createElement('span')
				placeholder.setAttribute('style', 'display: inline-block; width: ' + placeholderWidth + 'px; height: ' + placeholderHeight + 'px;')
				this.$refs.transcriptParagraph.prepend(placeholder)
				this.transcripts.unshift(placeholder)
			}

			return true
		},
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
