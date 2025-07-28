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
