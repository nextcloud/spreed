<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul :class="'placeholder-list placeholder-list--' + type">
		<li v-for="(item, index) in placeholderData" :key="index" class="placeholder-item">
			<div v-if="type !== 'event-cards'"
				class="placeholder-item__avatar"
				:style="{ '--avatar-size': item.avatarSize }">
				<div class="placeholder-item__avatar-circle" />
			</div>
			<div class="placeholder-item__content" :style="{'--last-line-width': item.width}">
				<div v-for="idx in item.amount" :key="idx" class="placeholder-item__content-line" />
			</div>
			<div v-if="type === 'messages'" class="placeholder-item__info" />
		</li>
	</ul>
</template>

<script>
import { AVATAR } from '../../constants.ts'

export default {
	name: 'LoadingPlaceholder',

	props: {
		type: {
			type: String,
			required: true,
			validator(value) {
				return ['conversations', 'messages', 'participants', 'event-cards'].includes(value)
			},
		},
		count: {
			type: Number,
			default: 5,
		},
	},

	computed: {
		placeholderData() {
			const data = []
			for (let i = 0; i < this.count; i++) {
				// set up amount of lines in skeleton and generate random widths for last line
				data.push({
					amount: this.type === 'messages'
						? 4
						: this.type === 'conversations' ? 2 : 1,
					width: this.type === 'participants'
						? '60%'
						: this.type === 'event-cards' ? '100%' : (Math.floor(Math.random() * 40) + 30) + '%',
					avatarSize: (this.type === 'messages' ? AVATAR.SIZE.SMALL : AVATAR.SIZE.DEFAULT) + 'px',
				})
			}
			return data
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.placeholder-list {
	width: 100%;
	transform: translateZ(0); // enable hardware acceleration
}

.placeholder-item {
	display: flex;
	gap: 8px;
	width: 100%;

	&__avatar {
		flex-shrink: 0;

		&-circle {
			height: var(--avatar-size);
			width: var(--avatar-size);
			border-radius: var(--avatar-size);
		}
	}

	&__content {
		display: flex;
		flex-direction: column;
		width: 100%;

		&-line {
			margin: 5px 0 4px;
			width: 100%;
			height: 15px;

			&:last-child {
				width: var(--last-line-width);
			}
		}
	}
}

// Conversations placeholder ruleset
.placeholder-list--conversations {
	.placeholder-item {
		margin: 2px 0;
		padding: 8px 10px;

		&__content {
			width: 70%;
		}
	}
}

// Messages placeholder ruleset
.placeholder-list--messages {
	max-width: $messages-list-max-width;
	margin: auto;

	.placeholder-item {
		padding-inline-end: 8px;

		&__avatar {
			width: 48px;
			padding: 20px 8px 0;
		}

		&__content {
			max-width: $messages-text-max-width;
			padding: 12px 0;

			&-line {
				margin: 4px 0 3px;

				&:first-child {
					margin-bottom: 9px;
					width: 20%;
				}
			}
		}

		&__info {
			width: 100px;
			height: 15px;
			margin-block: var(--default-clickable-area) 0;
			margin-inline: 8px var(--default-clickable-area);
			animation-delay: 0.8s;
		}
	}
}

// Participants placeholder ruleset
.placeholder-list--participants {
	.placeholder-item {
		--padding: calc(var(--default-grid-baseline) * 2);
		gap: calc(var(--default-grid-baseline) * 2);
		padding: calc(var(--padding) * 3 / 2) var(--padding) var(--padding);
		height: 59px;
		align-items: center;

		&__avatar {
			margin: auto;
		}
	}
}

// Event cards placeholder ruleset
.placeholder-list--event-cards {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
	flex-wrap: nowrap;
	overflow: hidden;

	.placeholder-item {
		&__content {
			width: 300px;

			&-line {
				margin: 0;
				height: 225px;
				background-color: var(--color-placeholder-light);
				border-radius: var(--border-radius-large);
			}
		}
	}
}

// Animation
.placeholder-item__avatar-circle,
.placeholder-item__content-line,
.placeholder-item__info {
	background-size: 200vw;
	background-image: linear-gradient(90deg, var(--color-placeholder-dark) 65%, var(--color-placeholder-light) 70%, var(--color-placeholder-dark) 75%);
	animation: loading-animation 3s forwards infinite linear;
	will-change: background-position;
}

@keyframes loading-animation {
	0% {
		background-position: 0;
	}
	100% {
		background-position: 140vw;
	}
}
</style>
