<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="video-backgroundbackground">
		<ResizeObserver
			v-if="gridBlur === ''"
			class="observer"
			@notify="setBlur" />
		<div class="darken" />
		<img
			v-if="hasPicture"
			:src="backgroundImage"
			:style="gridBlur ? gridBlur : blur"
			class="video-background__picture"
			alt="">
		<div v-else
			:style="{'background-color': backgroundColor }"
			class="video-background" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { ResizeObserver } from 'vue-resize'

export default {
	name: 'VideoBackground',
	components: {
		ResizeObserver,
	},

	props: {
		displayName: {
			type: String,
			default: null,
		},
		user: {
			type: String,
			default: '',
		},
		gridBlur: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			hasPicture: false,
			blur: '',
		}
	},

	computed: {
		backgroundColor() {
			// If the prop is empty. We're not checking for the default value
			// because the user's displayName might be '?'
			if (!this.displayName) {
				return `var(--color-text-maxcontrast)`
			} else {
				const color = this.displayName.toRgb()
				return `rgb(${color.r}, ${color.g}, ${color.b})`
			}
		},
		backgroundImage() {
			return generateUrl(`avatar/${this.user}/300`)
		},
	},

	async beforeMount() {
		if (!this.user) {
			return
		}

		try {
			const response = await axios.get(generateUrl(`avatar/${this.user}/300`))
			if (response.headers[`x-nc-iscustomavatar`] === '1') {
				this.hasPicture = true
			}
		} catch (exception) {
			console.debug(exception)
		}
	},

	methods: {
		// Calculate the background blur based on the hight of the background element
		setBlur({ width, height }) {
			// The amount of blur
			const amount = this.$store.getters.videoBackgroundBlur
			// Represents the surface of the element
			const surfaceMultiplier = (width * height) / 1000
			// Calculate the blur
			this.blur = `filter: blur(${surfaceMultiplier * amount}px)`

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
		transform: translate(-50%,-50%)
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

.observer {
	position: absolute;
}

</style>
