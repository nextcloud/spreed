<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="transcript-block">
		<div class="transcript-block__avatar">
			<AvatarWrapper :id="actorId"
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
			<p class="transcript-block__chunks">
				<span v-for="(item, index) in chunksWithSeparator"
					ref="chunks"
					:key="index">
					{{ item }}
				</span>
			</p>
		</div>
	</div>
</template>

<script lang="ts">
import type { PropType, StyleValue } from 'vue'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import { ATTENDEE, AVATAR } from '../../../constants.ts'
import { getDisplayNameWithFallback } from '../../../utils/getDisplayName.ts'

declare module 'vue' {
	interface TypeRefs {
		chunks: undefined | Array<HTMLDivElement>
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
			type: Array as PropType<Array<string>>,
			required: true,
		},
	},

	data() {
		return {
			AVATAR,
			lines: [] as Array<{ lastChunkIndex: number }>,
		}
	},

	computed: {
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

		chunksWithSeparator() {
			const chunksWithSeparator = [] as Array<string>

			if (!this.chunks.length) {
				return chunksWithSeparator
			}

			chunksWithSeparator.push(this.chunks[0])

			for (let i = 1; i < this.chunks.length; i++) {
				chunksWithSeparator.push(' ' + this.chunks[i])
			}

			return chunksWithSeparator
		},
	},

	methods: {
		resetLines() {
			this.lines = []
		},

		updateLines() {
			if (!this.$refs.chunks || !this.$refs.chunks.length) {
				return
			}

			if (!this.lines.length) {
				const firstChunkClientRectsLength = this.$refs.chunks[0].getClientRects().length

				// If there is a single chunk and it has several bounding
				// rectangles each rectangle will be in its own line.
				for (let i = 0; i < firstChunkClientRectsLength; i++) {
					this.lines.push({
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

				// Separators are inline rather than inline-block, so they have
				// different top and bottom boundaries and they should not be
				// taken into account.
				if (nextChunkElement.classList.contains('separator')) {
					continue
				}

				const nextChunkElementClientRects = nextChunkElement.getClientRects()
				const nextChunkElementTop = nextChunkElementClientRects[0].top

				// If the first bounding rectangle has the same top value as the
				// last known one they will be in the same line. Otherwise it
				// will be in a new line.
				if (nextChunkElementTop === lastKnownChunkElementTop) {
					this.lines.at(-1)!.lastChunkIndex = i
				} else {
					this.lines.push({
						lastChunkIndex: i,
					})
				}

				// Any bounding rectangle after the first one will be in its own
				// line.
				for (let j = 1; j < nextChunkElementClientRects.length; j++) {
					this.lines.push({
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
