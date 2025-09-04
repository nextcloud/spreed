<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="transcript-block"
		:style="transcriptBlockStyle">
		<div class="transcript-block__avatar">
			<AvatarWrapper
				:id="actorId"
				:token="token"
				:name="actorDisplayName"
				:source="actorType"
				:size="AVATAR.SIZE.SMALL"
				:disable-menu="true" />
		</div>
		<div class="transcript-block__text">
			<p class="transcript-block__author">
				{{ actorInfo }}
			</p>
			<p
				ref="chunksWrapper"
				class="transcript-block__chunks">
				<span
					v-for="(item, index) in chunksWithSeparator"
					ref="chunks"
					:key="index"
					:lang="item.languageId">
					{{ item.message }}
				</span>
			</p>
		</div>
	</div>
</template>

<script lang="ts">
import type { PropType, StyleValue } from 'vue'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import { ATTENDEE, AVATAR } from '../../../constants.ts'
import { useLiveTranscriptionStore } from '../../../stores/liveTranscription.ts'
import { getDisplayNameWithFallback } from '../../../utils/getDisplayName.ts'

declare module 'vue' {
	interface TypeRefs {
		chunksWrapper: HTMLParagraphElement
		chunks: undefined | Array<HTMLSpanElement>
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
}

interface Chunk {
	message: string
	languageId: string
	final: boolean
}

interface ChunkElementData {
	message: string
	languageId: string
}

export type {
	Chunk,
}

export default {
	name: 'TranscriptBlock',

	components: {
		AvatarWrapper,
	},

	props: {
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * The CallParticipantModel for the participant being transcribed.
		 */
		model: {
			type: Object as PropType<CallParticipantModel>,
			required: true,
		},

		/**
		 * The transcript chunks.
		 */
		chunks: {
			type: Array as PropType<Array<Chunk>>,
			required: true,
		},

		/**
		 * Whether the transcript is written right to left.
		 */
		rightToLeft: {
			type: Boolean,
			required: false,
			default: false,
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
			AVATAR,
			resizeObserver: null as null | ResizeObserver,
			lines: [] as Array<{
				firstChunkIndex: number
				lastChunkIndex: number
			}>,
		}
	},

	computed: {
		transcriptBlockStyle() {
			return {
				direction: this.rightToLeft ? 'rtl' : 'ltr',
			} as StyleValue
		},

		actorId() {
			return this.model.attributes.actorId || ''
		},

		actorType() {
			return this.model.attributes.actorType || ''
		},

		actorDisplayName() {
			return this.model.attributes.name || ''
		},

		actorDisplayNameWithFallback() {
			return getDisplayNameWithFallback(this.actorDisplayName, this.actorType)
		},

		remoteServer() {
			return this.actorType === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
				? '(' + this.actorId.split('@').pop() + ')'
				: ''
		},

		actorInfo() {
			return [this.actorDisplayNameWithFallback, this.remoteServer]
				.filter((value) => value).join(' ')
		},

		liveTranscriptionLanguages() {
			const liveTranscriptionLanguages = this.liveTranscriptionStore.getLiveTranscriptionLanguages()
			if (!liveTranscriptionLanguages) {
				return {}
			}

			return liveTranscriptionLanguages
		},

		chunksWithSeparator() {
			const chunksWithSeparator = [] as Array<ChunkElementData>

			if (!this.chunks.length) {
				return chunksWithSeparator
			}

			// The returned languageId is a BCP 47 language tag (to be used in
			// the HTML "lang" attribute), but the language and region may be
			// separated by "_" in the language metadata, so it needs to be
			// replaced by "-".

			chunksWithSeparator.push({
				message: this.chunks[0].message,
				languageId: this.chunks[0].languageId.replace('_', '-'),
			})

			for (let i = 1; i < this.chunks.length; i++) {
				const separator = this.getSeparatorBetweenChunks(this.chunks[i - 1], this.chunks[i])

				chunksWithSeparator.push({
					message: separator + this.chunks[i].message,
					languageId: this.chunks[i].languageId.replace('_', '-'),
				})
			}

			return chunksWithSeparator
		},
	},

	mounted() {
		this.resizeObserver = new ResizeObserver(this.handleChunksWrapperResize)
		this.resizeObserver.observe(this.$refs.chunksWrapper)
	},

	beforeUnmount() {
		this.resizeObserver!.disconnect()
	},

	methods: {
		reset() {
			this.lines = []

			this.$refs.chunksWrapper!.style.removeProperty('min-height')
		},

		handleChunksWrapperResize(entries: ResizeObserverEntry[], observer: ResizeObserver) {
			if (!this.$refs.chunksWrapper) {
				return
			}

			const height = parseFloat(window.getComputedStyle(this.$refs.chunksWrapper).getPropertyValue('height'))
			const minHeight = parseFloat(window.getComputedStyle(this.$refs.chunksWrapper).getPropertyValue('min-height'))

			if (height > minHeight || Number.isNaN(minHeight)) {
				this.$refs.chunksWrapper.style.setProperty('min-height', `${height}px`)
			}
		},

		removeLastChunkFromLines() {
			if (!this.lines.length) {
				return
			}

			const lastKnownChunkIndex = this.lines.at(-1)!.lastChunkIndex

			while (this.lines.length && this.lines.at(-1)!.firstChunkIndex === this.lines.at(-1)!.lastChunkIndex) {
				this.lines.splice(-1, 1)
			}

			if (this.lines.length && this.lines.at(-1)!.lastChunkIndex === lastKnownChunkIndex) {
				this.lines.at(-1)!.lastChunkIndex--
			}
		},

		updateLines() {
			if (!this.$refs.chunks || !this.$refs.chunks.length) {
				return
			}

			// Remove information of last chunk to regenerate it, as it could
			// have been updated and thus its lines could have changed.
			this.removeLastChunkFromLines()

			if (!this.lines.length) {
				const firstChunkClientRectsLength = this.$refs.chunks[0].getClientRects().length

				// If there is a single chunk and it has several bounding
				// rectangles each rectangle will be in its own line.
				for (let i = 0; i < firstChunkClientRectsLength; i++) {
					this.lines.push({
						firstChunkIndex: 0,
						lastChunkIndex: 0,
					})
				}
			}

			const lastKnownChunkIndex = this.lines.at(-1)!.lastChunkIndex
			if (lastKnownChunkIndex >= this.$refs.chunks.length - 1) {
				return
			}

			let lastKnownChunkElement = this.$refs.chunks[lastKnownChunkIndex]
			let lastKnownChunkElementTop = lastKnownChunkElement.getClientRects()[lastKnownChunkElement.getClientRects().length - 1].top

			for (let i = lastKnownChunkIndex + 1; i < this.$refs.chunks.length; i++) {
				const nextChunkElement = this.$refs.chunks[i]

				const nextChunkElementClientRects = nextChunkElement.getClientRects()
				const nextChunkElementTop = nextChunkElementClientRects[0].top

				// If the first bounding rectangle has the same top value as the
				// last known one they will be in the same line. Otherwise it
				// will be in a new line.
				if (nextChunkElementTop === lastKnownChunkElementTop) {
					this.lines.at(-1)!.lastChunkIndex = i
				} else {
					this.lines.push({
						firstChunkIndex: i,
						lastChunkIndex: i,
					})
				}

				// Any bounding rectangle after the first one will be in its own
				// line.
				for (let j = 1; j < nextChunkElementClientRects.length; j++) {
					this.lines.push({
						firstChunkIndex: i,
						lastChunkIndex: i,
					})
				}

				lastKnownChunkElement = nextChunkElement
				lastKnownChunkElementTop = lastKnownChunkElement.getClientRects()[lastKnownChunkElement.getClientRects().length - 1].top
			}
		},

		getLineBoundaries() {
			this.updateLines()

			const lineHeight = parseFloat(window.getComputedStyle(this.$el).getPropertyValue('line-height'))

			let clientRectIndex = 0

			return this.lines.map((line, index) => {
				const clientRectsOfLastChunkInLine = this.$refs.chunks![line.lastChunkIndex].getClientRects()

				if (index > 0 && line.lastChunkIndex === this.lines[index - 1].lastChunkIndex) {
					clientRectIndex++
				} else {
					clientRectIndex = 0
				}

				const currentClientRectsOfLastChunkInLine = clientRectsOfLastChunkInLine[clientRectIndex]

				// Chunks are shown as inline spans, which do not have the full
				// line height. The spans are vertically centered on the line,
				// so there is the same extra space at the top and at the
				// bottom.
				const chunkHeight = currentClientRectsOfLastChunkInLine.bottom - currentClientRectsOfLastChunkInLine.top
				const chunkToLineHeightDifference = lineHeight - chunkHeight

				return {
					top: currentClientRectsOfLastChunkInLine.top - (chunkToLineHeightDifference / 2),
					bottom: currentClientRectsOfLastChunkInLine.bottom + (chunkToLineHeightDifference / 2),
				}
			})
		},

		getSeparatorBetweenChunks(chunk1: Chunk, chunk2: Chunk) {
			if (chunk1.languageId !== chunk2.languageId) {
				return ' '
			}

			if (this.liveTranscriptionLanguages[chunk1.languageId]?.metadata) {
				return this.liveTranscriptionLanguages[chunk1.languageId].metadata.separator
			}

			return ' '
		},
	},
}
</script>

<style lang="scss" scoped>
.transcript-block {
	display: flex;
	flex-direction: row;
	align-items: flex-start;
	align-self: end;
	width: 100%;

	background-color: rgba(34, 34, 34, 0.8);
	color: white;

	&__avatar {
		position: sticky;
		top: 0;
		padding: calc(2 * var(--default-grid-baseline));
		margin-top: calc(2 * var(--default-grid-baseline));
	}

	&__text {
		display: flex;
		flex-direction: column;

		padding-inline-end: var(--default-grid-baseline);
	}

	&__author {
		color: var(--color-text-maxcontrast);
		flex-shrink: 0;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;

		/* Move the author closer to the chunks while keeping the line height,
		 * but not too much to avoid "overflowing" the line (which could
		 * partially show the author after scrolling to another line).
		 */
		 margin-top: 4px;
		 margin-bottom: -4px;
	}

	&__chunks {
		&::first-letter {
			text-transform: capitalize;
		}
	}
}
</style>
