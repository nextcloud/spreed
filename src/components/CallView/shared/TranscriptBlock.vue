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
			<p class="transcript-block__author" aria-level="4">
				{{ actorInfo }}
			</p>
			<p class="transcript-block__chunks">
				<span v-for="(item, index) in chunksWithSeparator"
					ref="chunks"
					:key="index"
					:class="item.class">
					{{ item.text }}
				</span>
			</p>
		</div>
	</div>
</template>

<script>
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import { ATTENDEE, AVATAR } from '../../../constants.ts'
import { getDisplayNameWithFallback } from '../../../utils/getDisplayName.ts'

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
			type: Object,
			required: true,
		},

		/**
		 * The transcript chunks.
		 */
		chunks: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			AVATAR,
			lines: [],
		}
	},

	computed: {
		actorId() {
			if (this.model.attributes.actorId) {
				return this.model.attributes.actorId
			}

			if (this.model.attributes.userId) {
				return this.model.attributes.userId
			}

			return null
		},

		actorType() {
			if (this.model.attributes.actorType) {
				return this.model.attributes.actorType
			}

			return this.participantUserId
				? ATTENDEE.ACTOR_TYPE.USERS
				: ATTENDEE.ACTOR_TYPE.GUESTS
		},

		actorDisplayName() {
			if (this.model.attributes.name) {
				return this.model.attributes.name
			}

			return ''
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
			const chunksWithSeparator = []

			if (!this.chunks.length) {
				return chunksWithSeparator
			}

			chunksWithSeparator.push({
				text: this.chunks[0],
				class: '',
			})

			for (let i = 1; i < this.chunks.length; i++) {
				chunksWithSeparator.push({
					text: ' ',
					class: 'separator',
				})
				chunksWithSeparator.push({
					text: this.chunks[i],
					class: '',
				})
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

			const lastKnownChunkIndex = this.lines.at(-1).lastChunkIndex
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
					this.lines.at(-1).lastChunkIndex = i
				} else {
					this.lines.push({
						lastChunkIndex: i,
					})
				}

				// Any bounding rectangle after the first one will be in its own
				// line.
				for (let i = 1; i < nextChunkElementClientRects.length; i++) {
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

			return this.lines.map((line) => {
				const firstClientRectsOfLastChunkInLine = this.$refs.chunks[line.lastChunkIndex].getClientRects()[0]
				return {
					top: firstClientRectsOfLastChunkInLine.top,
					bottom: firstClientRectsOfLastChunkInLine.bottom,
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
	}

	&__author {
		padding-inline-start: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
		flex-shrink: 0;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__chunks {
		span {
			display: inline-block;
		}

		span.separator {
			display: inline;
		}
	}
}
</style>
