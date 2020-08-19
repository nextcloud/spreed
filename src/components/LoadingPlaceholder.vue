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
	<ul>
		<!-- Placeholder animation -->
		<svg class="placeholder-gradient">
			<defs>
				<linearGradient id="placeholder-gradient">
					<stop offset="0%" :stop-color="light">
						<animate attributeName="stop-color"
							:values="`${light}; ${light}; ${dark}; ${dark}; ${light}`"
							dur="2s"
							repeatCount="indefinite" />
					</stop>
					<stop offset="100%" :stop-color="dark">
						<animate attributeName="stop-color"
							:values="`${dark}; ${light}; ${light}; ${dark}; ${dark}`"
							dur="2s"
							repeatCount="indefinite" />
					</stop>
				</linearGradient>
			</defs>
		</svg>

		<!-- Placeholders -->
		<li v-for="placeholder in count" :key="placeholder">
			<svg
				v-if="type === 'conversations'"
				class="conversation-placeholder"
				xmlns="http://www.w3.org/2000/svg"
				fill="url(#placeholder-gradient)">
				<circle class="conversation-placeholder-icon" />
				<rect class="conversation-placeholder-line-one" />
				<rect class="conversation-placeholder-line-two" :style="{width: `calc(${randWidth()}%)`}" />
			</svg>
			<svg
				v-if="type === 'messages'"
				class="message-placeholder"
				xmlns="http://www.w3.org/2000/svg"
				fill="url(#placeholder-gradient)">
				<circle class="message-placeholder-icon" />
				<rect class="message-placeholder-line-one" />
				<rect class="message-placeholder-line-two" />
				<rect class="message-placeholder-line-three" />
				<rect class="message-placeholder-line-four" :style="{width: `calc(${randWidth()}%)`}" />
			</svg>
		</li>
	</ul>
</template>

<script>
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

	data() {
		return {
			light: null,
			dark: null,
		}
	},

	mounted() {
		const styles = getComputedStyle(document.documentElement)
		this.dark = styles.getPropertyValue('--color-placeholder-dark')
		this.light = styles.getPropertyValue('--color-placeholder-light')
	},

	methods: {
		randWidth() {
			return Math.floor(Math.random() * 20) + 30
		},
	},
}
</script>

<style lang="scss" scoped>
	$clickable-area: 44px;
	$margin: 8px;

	ul {
		width: 100%;
		position: relative;
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
		width: 800px;
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

</style>
