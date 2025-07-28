<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="transcript">
		<TranscriptBlock v-for="item in transcriptBlocks"
			:key="item.id"
			:token="token"
			:model="item.model"
			:chunks="item.chunks" />
	</div>
</template>

<script lang="ts">
import type { PropType } from 'vue'

import TranscriptBlock from './TranscriptBlock.vue'

interface CallParticipantModel {
	attributes: {
		peerId: string
		actorId: string | null | undefined
		actorType: string | null | undefined
		userId: string | null | undefined
		name: string | null | undefined
	}
	/* eslint-disable-next-line @typescript-eslint/no-explicit-any */
	on: (event: string, handler: (model: CallParticipantModel, ...args: any[]) => void) => void
	/* eslint-disable-next-line @typescript-eslint/no-explicit-any */
	off: (event: string, handler: (model: CallParticipantModel, ...args: any[]) => void) => void
}

interface TranscriptBlockData {
	id: number
	model: CallParticipantModel
	chunks: Array<string>
}

export default {
	name: 'LiveTranscriptionRenderer',

	components: {
		TranscriptBlock,
	},

	props: {
		/**
		 * The conversation token
		 */
		token: {
			type: String,
			required: true,
		},

		callParticipantModels: {
			type: Array as PropType<Array<CallParticipantModel>>,
			required: true,
		},
	},

	data() {
		return {
			registeredModels: {} as { [key: string]: CallParticipantModel },
			transcriptBlocks: [] as TranscriptBlockData[],
		}
	},

	watch: {
		callParticipantModels: {
			immediate: true,
			handler(models: Array<CallParticipantModel>) {
				// Subscribe connected models for transcript events
				const addedModels = models.filter((model) => !this.registeredModels[model.attributes.peerId])
				addedModels.forEach((addedModel) => {
					this.registeredModels[addedModel.attributes.peerId] = addedModel
					this.registeredModels[addedModel.attributes.peerId].on('transcript', this.handleTranscript)
				})

				// Unsubscribe disconnected models
				const removedModelIds = Object.keys(this.registeredModels).filter((registeredModelId) => !models.find((model) => model.attributes.peerId === registeredModelId))
				removedModelIds.forEach((removedModelId) => {
					this.registeredModels[removedModelId].off('transcript', this.handleTranscript)
					delete this.registeredModels[removedModelId]
				})
			},
		},
	},

	beforeUnmount() {
		Object.keys(this.registeredModels).forEach((modelId) => {
			this.registeredModels[modelId].off('transcript', this.handleTranscript)
			delete this.registeredModels[modelId]
		})
	},

	methods: {
		/**
		 * Handle a new received transcript.
		 *
		 * The transcript is added to the last block if it comes from the same
		 * participant, or a new block is added if it comes from another one.
		 *
		 * @param {object} model the CallParticipantModel for the participant
		 *        that was transcribed.
		 * @param {string} message the transcribed message.
		 */
		handleTranscript(model: CallParticipantModel, message: string) {
			let lastTranscriptBlock = this.transcriptBlocks.at(-1)

			if (lastTranscriptBlock?.model.attributes.peerId !== model.attributes.peerId) {
				const transcriptBlock = {
					id: lastTranscriptBlock ? lastTranscriptBlock.id + 1 : 0,
					model,
					chunks: [],
				}

				this.transcriptBlocks.push(transcriptBlock)

				lastTranscriptBlock = transcriptBlock
			}

			lastTranscriptBlock.chunks.push(message)
		},
	},
}
</script>

<style lang="scss" scoped>
.transcript {
	/**
	 * A unitless value in line-height would be a multiplier on the font size,
	 * but --line-height is used for other properties like max-height that
	 * require a unit, so the variable needs to be explicitly multiplied by the
	 * font size.
	 */
	--line-height: calc(var(--default-font-size) * 2);
	position: absolute;
	bottom: 20px;
	inset-inline: 20%;

	line-height: var(--line-height);
	max-height: calc(var(--line-height) * 4);
	overflow: hidden;

	display: flex;
	align-items: center;
	flex-direction: column;
	justify-content: flex-end;

	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
	backdrop-filter: var(--filter-background-blur);

	z-index: 1;
}

@media (height <= calc(var(--line-height) * 8)) {
	.transcript {
		max-height: calc(var(--line-height) * 2);
	}
}
@media (max-width: 768px) {
	.transcript {
		inset-inline: 10%;
	}
}
@media (max-width: 512px) {
	.transcript {
		inset-inline: 5%;
	}
}
</style>
