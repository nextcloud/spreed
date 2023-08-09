<!--
  - @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="placeholder-main">
		<!-- Placeholder animation -->
		<template v-for="(suffix, gradientIndex) in ['-regular', '-reverse']">
			<svg :key="'gradient' + suffix" :class="'placeholder-gradient placeholder-gradient' + suffix">
				<defs>
					<linearGradient :id="'placeholder-gradient' + suffix">
						<stop offset="0%" :stop-color="(gradientIndex === 0) ? colorPlaceholderLight : colorPlaceholderDark" />
						<stop offset="100%" :stop-color="(gradientIndex === 0) ? colorPlaceholderDark : colorPlaceholderLight" />
					</linearGradient>
				</defs>
			</svg>

			<ul :key="'list' + suffix" :class="'placeholder-list placeholder-list' + suffix">
				<li v-for="(width, index) in placeholderData" :key="'placeholder' + suffix + index">
					<svg v-if="type === 'conversations'"
						class="conversation-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient' + suffix + ')'">
						<circle class="conversation-placeholder-icon" />
						<rect class="conversation-placeholder-line-one" />
						<rect class="conversation-placeholder-line-two" :style="width" />
					</svg>
					<svg v-if="type === 'messages'"
						class="message-placeholder"
						xmlns="http://www.w3.org/2000/svg"
						:fill="'url(#placeholder-gradient' + suffix + ')'">
						<circle class="message-placeholder-icon" />
						<rect class="message-placeholder-line-one" />
						<rect class="message-placeholder-line-two" />
						<rect class="message-placeholder-line-three" />
						<rect class="message-placeholder-line-four" :style="width" />
					</svg>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
/**
 * Displays a loading placeholder for conversation messages.
 *
 * The gradient animation is achieved by having two placeholder elements,
 * with opposite gradient directions (regular and reverse) displayed on top
 * of each other (overlapped with position: absolute) and then fading between
 * each other by animating the opacities.
 */

const bodyStyles = window.getComputedStyle(document.body)
const colorPlaceholderDark = bodyStyles.getPropertyValue('--color-placeholder-dark')
const colorPlaceholderLight = bodyStyles.getPropertyValue('--color-placeholder-light')

export default {
	name: 'LoadingPlaceholder',

	props: {
		type: {
			type: String,
			required: true,
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
				// generate random widths
				data.push('width: ' + (Math.floor(Math.random() * 20) + 30) + '%')
			}
			return data
		},
	},
}
</script>

<style lang="scss" scoped>
	@import '../assets/variables';

	$clickable-area: 44px;
	$margin: 8px;

	.placeholder-main {
		max-width: $messages-list-max-width;
		position: relative;
		margin: auto;
		padding: 0;
	}

	.placeholder-list {
		position: absolute;
		translate: translateZ(0);
	}

	.placeholder-list-regular {
		animation: pulse 2s;
		animation-iteration-count: infinite;
		animation-timing-function: linear;
	}

	.placeholder-list-reverse {
		animation: pulse-reverse 2s;
		animation-iteration-count: infinite;
		animation-timing-function: linear;
	}

	.placeholder-gradient {
		position: fixed;
		height: 0;
		width: 0;
		z-index: -1;
	}

	.conversation-placeholder,
	.message-placeholder {

		&-icon {
			width: $clickable-area;
			height: $clickable-area;
			cx: calc(#{$clickable-area} / 2);
			cy: calc(#{$clickable-area} / 2);
			r: calc(#{$clickable-area} / 2);
		}
	}

	.conversation-placeholder {
		width: calc(100% - 2 * #{$margin});
		height: $clickable-area;
		margin: $margin;

		&-line-one,
		&-line-two {
			width: calc(100% - #{$margin + $clickable-area});
			position: relative;
			height: 1em;
			x: $margin + $clickable-area;
		}

		&-line-one {
			y: 5px;
		}

		&-line-two {
			y: 25px;
		}
	}

	.message-placeholder {
		width: $messages-list-max-width;
		height: calc(#{$clickable-area} * 2);
		margin: $margin auto;
		padding: 0 $margin;
		display: block;

		&-line-one,
		&-line-two,
		&-line-three,
		&-line-four {
			width: 640px;
			height: 1em;
			x: $margin + $clickable-area;
		}

		&-line-one {
			y: 5px;
			width: 175px;
		}

		&-line-two {
			y: 25px;
		}

		&-line-three {
			y: 45px;
		}

		&-line-four {
			y: 65px;
		}
	}

	@keyframes pulse {
		0% {
			opacity: 1;
		}
		50% {
			opacity: 0;
		}
		100% {
			opacity: 1;
		}
	}

	@keyframes pulse-reverse {
		0% {
			opacity: 0;
		}
		50% {
			opacity: 1;
		}
		100% {
			opacity: 0;
		}
	}

</style>
