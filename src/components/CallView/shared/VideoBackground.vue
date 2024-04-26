<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="video-backgroundbackground">
		<div :style="{'background-color': backgroundColor }"
			class="video-background" />
		<div ref="darkener"
			class="darken" />
	</div>
</template>

<script>
import usernameToColor from '@nextcloud/vue/dist/Functions/usernameToColor.js'

export default {
	name: 'VideoBackground',

	props: {
		displayName: {
			type: String,
			default: null,
		},
		user: {
			type: String,
			default: '',
		},
	},

	computed: {
		backgroundColor() {
			// If the prop is empty. We're not checking for the default value
			// because the user's displayName might be '?'
			if (!this.displayName) {
				return 'var(--color-text-maxcontrast)'
			} else {
				const color = usernameToColor(this.displayName)
				return `rgb(${color.r}, ${color.g}, ${color.b})`
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.video-background {
	position: absolute;
	left: 0;
	top: 0;
	height: 100%;
	width: 100%;
	&__picture {
		/* Make pic to at least 100% wide and tall */
		min-width: 105%;
		min-height: 105%;

		/* Setting width & height to auto prevents the browser from stretching or squishing the pic */
		width: auto;
		height: auto;

		/* Center the video */
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%,-50%);
	}

	h3 {
		color: white;
		position: absolute;
		top: 40%;
		left: 50%;
		font-size: 50px;
		font-weight: 500;
		text-transform: uppercase;
	}
}

.darken {
	background-color: black;
	opacity: 12%;
	position: absolute;
	width: 100%;
	height: 100%;
	top: 0;
	left: 0;
}

</style>
