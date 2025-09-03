<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		ref="transcript"
		class="transcript">
		<TranscriptBlock
			v-for="item in transcriptBlocks"
			ref="transcriptBlocks"
			:key="item.id"
			:token="token"
			:model="item.model"
			:chunks="item.chunks"
			:right-to-left="item.rightToLeft" />
	</div>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { Chunk } from './TranscriptBlock.vue'

import TranscriptBlock from './TranscriptBlock.vue'
import { useLiveTranscriptionStore } from '../../../stores/liveTranscription.ts'

declare module 'vue' {
	interface TypeRefs {
		transcript: HTMLDivElement
		transcriptBlocks: undefined | Array<TranscriptBlock>
	}

	interface ComponentCustomProperties {
		$refs: TypeRefs
	}
}

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
	chunks: Array<Chunk>
	rightToLeft: boolean
}

interface BlockAndLine {
	block: number
	line: number
}

type TranscriptBlock = InstanceType<typeof TranscriptBlock>

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

	setup() {
		const liveTranscriptionStore = useLiveTranscriptionStore()

		return {
			liveTranscriptionStore,
		}
	},

	data() {
		return {
			registeredModels: {} as { [key: string]: CallParticipantModel },
			resizeObserver: null as null | ResizeObserver,
			transcriptBlocks: [] as TranscriptBlockData[],
			lastScrolledToBlockAndLine: null as null | BlockAndLine,
			pendingScrollToBottomLineByLine: undefined as undefined | ReturnType<typeof setTimeout>,
		}
	},

	computed: {
		liveTranscriptionLanguages() {
			const liveTranscriptionLanguages = this.liveTranscriptionStore.getLiveTranscriptionLanguages()
			if (!liveTranscriptionLanguages) {
				return {}
			}

			return liveTranscriptionLanguages
		},
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

	mounted() {
		this.resizeObserver = new ResizeObserver(this.handleResize)
		this.resizeObserver.observe(this.$refs.transcript)
	},

	beforeUnmount() {
		Object.keys(this.registeredModels).forEach((modelId) => {
			this.registeredModels[modelId].off('transcript', this.handleTranscript)
			delete this.registeredModels[modelId]
		})

		this.resizeObserver!.disconnect()

		clearTimeout(this.pendingScrollToBottomLineByLine)
	},

	methods: {
		/**
		 * Handle resizings of the transcript element.
		 *
		 * After the transcript is resized the previous lines might have
		 * changed. For simplicity, and given that it was probably at the bottom
		 * or close to it already, rather than trying to keep the same visible
		 * lines the transcript is just scrolled to the bottom; any pending
		 * scroll to bottom is also cancelled.
		 *
		 * @param entries
		 * @param observer
		 */
		handleResize(entries: ResizeObserverEntry[], observer: ResizeObserver) {
			if (!this.$refs.transcriptBlocks) {
				return
			}

			for (let i = 0; i < this.$refs.transcriptBlocks.length; i++) {
				this.$refs.transcriptBlocks[i].resetLines()
			}

			this.$refs.transcript.scrollTo({
				top: this.$refs.transcript.scrollHeight,
			})

			// This should not happen, but just in case
			if (!this.lastScrolledToBlockAndLine) {
				this.lastScrolledToBlockAndLine = {
					block: 0,
					line: 0,
				}
			}

			this.lastScrolledToBlockAndLine.block = this.$refs.transcriptBlocks.length - 1

			const lastTranscriptBlock = this.$refs.transcriptBlocks[this.lastScrolledToBlockAndLine.block]
			const lastTranscriptBlockLineBoundaries = lastTranscriptBlock.getLineBoundaries()

			this.lastScrolledToBlockAndLine.line = lastTranscriptBlockLineBoundaries.length - 1

			if (this.pendingScrollToBottomLineByLine) {
				clearTimeout(this.pendingScrollToBottomLineByLine)

				this.pendingScrollToBottomLineByLine = undefined
			}
		},

		/**
		 * Handle a new received transcript.
		 *
		 * The transcript is added to the last block if it comes from the same
		 * participant, or a new block is added if it comes from another one. A
		 * new block will be used even for the same participant if the text
		 * direction changed.
		 *
		 * @param model the CallParticipantModel for the participant
		 *        that was transcribed.
		 * @param message the transcribed message.
		 * @param languageId the ID of the language of the transcribed
		 *        message.
		 */
		handleTranscript(model: CallParticipantModel, message: string, languageId: string) {
			let lastTranscriptBlock = this.transcriptBlocks.at(-1)

			const messageIsRightToLeft = this.liveTranscriptionLanguages[languageId]?.metadata.rtl || false

			if (lastTranscriptBlock?.model.attributes.peerId !== model.attributes.peerId
				|| lastTranscriptBlock?.rightToLeft !== messageIsRightToLeft) {
				const transcriptBlock = {
					id: lastTranscriptBlock ? lastTranscriptBlock.id + 1 : 0,
					model,
					chunks: [],
					rightToLeft: messageIsRightToLeft,
				}

				this.transcriptBlocks.push(transcriptBlock)

				lastTranscriptBlock = transcriptBlock
			}

			const newTranscriptChunk = {
				message,
				languageId,
			}

			lastTranscriptBlock.chunks.push(newTranscriptChunk)

			this.$nextTick(() => {
				this.scrollToBottomLineByLine()
			})
		},

		/**
		 * Scroll to the bottom, one line at a time, with a small pause at each
		 * line.
		 *
		 * If there are no more lines the no longer visible blocks are removed.
		 */
		scrollToBottomLineByLine() {
			if (this.pendingScrollToBottomLineByLine) {
				return
			}

			if (!this.scrollToNextLine()) {
				this.removeNoLongerVisibleTranscriptBlocks()

				return
			}

			this.pendingScrollToBottomLineByLine = setTimeout(() => {
				this.pendingScrollToBottomLineByLine = undefined

				this.scrollToBottomLineByLine()
			}, 2000)
		},

		/**
		 * Scroll to the next line after the last visible one.
		 *
		 * @return {boolean} true if there was a line to scroll to, false
		 *         otherwise.
		 */
		scrollToNextLine() {
			if (!this.lastScrolledToBlockAndLine) {
				this.scrollToBlockAndLine(0, 0)

				return true
			}

			const lastScrolledToBlockLineBoundaries = this.$refs.transcriptBlocks![this.lastScrolledToBlockAndLine.block].getLineBoundaries()
			if (this.lastScrolledToBlockAndLine.line < lastScrolledToBlockLineBoundaries.length - 1) {
				this.scrollToBlockAndLine(this.lastScrolledToBlockAndLine.block, this.lastScrolledToBlockAndLine.line + 1)

				return true
			}

			if (this.lastScrolledToBlockAndLine.block < this.$refs.transcriptBlocks!.length - 1) {
				this.scrollToBlockAndLine(this.lastScrolledToBlockAndLine.block + 1, 0)

				return true
			}

			return false
		},

		/**
		 * Scroll to the given line in the given block.
		 *
		 * The bottom of the line will be aligned with the bottom of the
		 * transcript element (unless the internal area of the transcript is not
		 * large enough yet to be scrolled).
		 *
		 * @param block the index of the block in the current list of
		 *        blocks.
		 * @param line the index of the line in the current list of
		 *        lines of the block.
		 */
		scrollToBlockAndLine(block: number, line: number) {
			this.lastScrolledToBlockAndLine = {
				block,
				line,
			}

			const transcriptBoundaries = this.$refs.transcript.getBoundingClientRect()
			const transcriptTop = transcriptBoundaries.top
			const transcriptHeight = transcriptBoundaries.bottom - transcriptBoundaries.top

			const scrollToBlockLineBoundaries = this.$refs.transcriptBlocks![block].getLineBoundaries()
			const scrollToLineLineBoundaries = scrollToBlockLineBoundaries[line]
			const scrollToLineHeight = scrollToLineLineBoundaries.bottom - scrollToLineLineBoundaries.top

			const scrollToLineRelativeLineBoundaries = {
				top: scrollToLineLineBoundaries.top - transcriptTop,
				bottom: scrollToLineLineBoundaries.bottom - transcriptTop,
			}

			// Align bottom of line with bottom of transcript
			const scrollToTop = this.$refs.transcript.scrollTop
				+ (scrollToLineRelativeLineBoundaries.top - transcriptHeight)
				+ scrollToLineHeight

			this.$refs.transcript.scrollTo({
				top: scrollToTop,
				behavior: 'smooth',
			})
		},

		/**
		 * Remove all the transcript blocks fully above the top of the
		 * transcript element.
		 */
		removeNoLongerVisibleTranscriptBlocks() {
			const count = this.getNoLongerVisibleTranscriptBlocksCount()
			this.transcriptBlocks.splice(0, count)
			this.lastScrolledToBlockAndLine!.block = this.lastScrolledToBlockAndLine!.block - count

			// The same scroll position is expected to be automatically kept
			// after the elements are removed, so the scroll is not explicitly
			// adjusted.
		},

		/**
		 * @return {number} the number of no longer visible transcript blocks.
		 */
		getNoLongerVisibleTranscriptBlocksCount() {
			const transcriptTop = this.$refs.transcript.getBoundingClientRect().top

			let count = 0
			for (let i = 0; i < this.lastScrolledToBlockAndLine!.block; i++) {
				if (this.$refs.transcriptBlocks![i].$el.getBoundingClientRect().bottom > transcriptTop) {
					return count
				}

				count++
			}

			return count
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
