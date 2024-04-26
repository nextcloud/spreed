<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul :class="'placeholder-list placeholder-list--' + type"
		:style="{ '--colorPlaceholderLight': colorPlaceholderLight, '--colorPlaceholderDark': colorPlaceholderDark }">
		<li v-for="(item, index) in placeholderData" :key="index" class="placeholder-item">
			<div class="placeholder-item__avatar">
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
const bodyStyles = window.getComputedStyle(document.body)
const colorPlaceholderDark = bodyStyles.getPropertyValue('--color-placeholder-dark')
const colorPlaceholderLight = bodyStyles.getPropertyValue('--color-placeholder-light')

export default {
	name: 'LoadingPlaceholder',

	props: {
		type: {
			type: String,
			required: true,
			validator(value) {
				return ['conversations', 'messages', 'participants'].includes(value)
			},
		},
		count: {
			type: Number,
			default: 5,
		},
	},

	setup() {
		return {
			colorPlaceholderDark,
			colorPlaceholderLight,
		}
	},

	computed: {
		placeholderData() {
			const data = []
			for (let i = 0; i < this.count; i++) {
				// set up amount of lines in skeleton and generate random widths for last line
				data.push({
					amount: this.type === 'messages' ? 4 : this.type === 'conversations' ? 2 : 1,
					width: this.type === 'participants' ? '60%' : (Math.floor(Math.random() * 40) + 30) + '%',
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
			height: var(--default-clickable-area);
			width: var(--default-clickable-area);
			border-radius: var(--default-clickable-area);
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
		padding-right: 8px;
		&__avatar {
			width: 52px;
			padding: 18px 10px 0;

			&-circle {
				height: 32px;
				width: 32px;
				border-radius: 32px;
			}
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
			margin: var(--default-clickable-area) var(--default-clickable-area) 0 8px;
			animation-delay: 0.8s;
		}
	}
}

// Participants placeholder ruleset
.placeholder-list--participants {
	.placeholder-item {
		gap: 12px;
		margin: 4px 0;
		padding: 0 4px;
		height: 56px;
		align-items: center;

		&__avatar {
			margin: auto;
		}
	}
}

// Animation
.placeholder-item__avatar-circle,
.placeholder-item__content-line,
.placeholder-item__info {
	background-size: 200vw;
	background-image: linear-gradient(90deg, var(--colorPlaceholderDark) 65%, var(--colorPlaceholderLight) 70%, var(--colorPlaceholderDark) 75%);
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
