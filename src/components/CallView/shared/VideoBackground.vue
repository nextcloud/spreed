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
		<div
			ref="darkener"
			class="darken">
			<ResizeObserver
				v-if="gridBlur === 0"
				class="observer"
				@notify="setBlur" />
		</div>
		<img
			v-if="hasPicture"
			ref="backgroundImage"
			:src="backgroundImage"
			:style="backgroundStyle"
			class="video-background__picture"
			alt="">
		<div v-else
			:style="{'background-color': backgroundColor }"
			class="video-background" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import usernameToColor from '@nextcloud/vue/dist/Functions/usernameToColor'
import { generateUrl } from '@nextcloud/router'
import { ResizeObserver } from 'vue-resize'
import { getBuilder } from '@nextcloud/browser-storage'
import browserCheck from '../../../mixins/browserCheck'
import blur from '../../../utils/imageBlurrer'

const browserStorage = getBuilder('nextcloud').persist().build()

// note: this info is shared with the Avatar component
function getUserHasAvatar(userId) {
	const flag = browserStorage.getItem('user-has-avatar.' + userId)
	if (typeof flag === 'string') {
		return Boolean(flag)
	}
	return null
}

function setUserHasAvatar(userId, flag) {
	browserStorage.setItem('user-has-avatar.' + userId, flag)
}

export default {
	name: 'VideoBackground',
	components: {
		ResizeObserver,
	},

	mixins: [
		browserCheck,
	],

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
			type: Number,
			default: 0,
		},
	},

	data() {
		return {
			hasPicture: false,
			useCssBlurFilter: true,
			blur: 0,
			blurredBackgroundImage: null,
			blurredBackgroundImageCache: {},
			blurredBackgroundImageSource: null,
			isDestroyed: false,
		}
	},

	computed: {
		backgroundColor() {
			// If the prop is empty. We're not checking for the default value
			// because the user's displayName might be '?'
			if (!this.displayName) {
				return `var(--color-text-maxcontrast)`
			} else {
				const color = usernameToColor(this.displayName)
				return `rgb(${color.r}, ${color.g}, ${color.b})`
			}
		},
		backgroundImage() {
			return this.useCssBlurFilter ? this.backgroundImageUrl : this.blurredBackgroundImage
		},
		backgroundImageUrl() {
			if (!this.user) {
				return null
			}

			return generateUrl(`avatar/${this.user}/300`)
		},
		backgroundBlur() {
			return this.gridBlur ? this.gridBlur : this.blur
		},
		backgroundStyle() {
			if (!this.useCssBlurFilter) {
				return {}
			}

			return {
				filter: `blur(${this.backgroundBlur}px)`,
			}
		},
		// Special computed property to combine the properties that should be
		// watched to generate (or not) the blurred background image.
		generatedBackgroundBlur() {
			if (!this.hasPicture || this.useCssBlurFilter) {
				return false
			}

			if (!this.blurredBackgroundImageSource) {
				return false
			}

			return this.backgroundBlur
		},
	},

	watch: {
		backgroundImageUrl: {
			immediate: true,
			handler() {
				this.blurredBackgroundImageSource = null

				if (!this.backgroundImageUrl) {
					return
				}

				const image = new Image()
				image.onload = () => {
					this.blurredBackgroundImageSource = image
				}
				image.src = this.backgroundImageUrl
			},
		},
		generatedBackgroundBlur: {
			immediate: true,
			handler() {
				if (this.generatedBackgroundBlur === false) {
					return
				}

				this.generateBlurredBackgroundImage()
			},
		},
	},

	async beforeMount() {
		if (this.isChrome) {
			this.useCssBlurFilter = false
		}

		if (!this.user) {
			return
		}

		// check if hasAvatar info is already known
		const userHasAvatar = getUserHasAvatar(this.user)
		if (typeof userHasAvatar === 'boolean') {
			this.hasPicture = userHasAvatar
			return
		}

		try {
			const response = await axios.get(generateUrl(`avatar/${this.user}/300`))
			if (response.headers[`x-nc-iscustomavatar`] === '1') {
				this.hasPicture = true
				setUserHasAvatar(this.user, true)
			} else {
				setUserHasAvatar(this.user, false)
			}
		} catch (exception) {
			console.debug(exception)
		}
	},

	async mounted() {
		if (!this.gridBlur) {
			// Initialise blur
			this.setBlur({
				width: this.$refs['darkener'].clientWidth,
				height: this.$refs['darkener'].clientHeight,
			})
		}
	},

	beforeDestroy() {
		this.isDestroyed = true
	},

	methods: {
		// Calculate the background blur based on the height of the background element
		setBlur({ width, height }) {
			this.blur = this.$store.getters.getBlurRadius(width, height)
		},

		generateBlurredBackgroundImage() {
			// Reset image source so the width and height are adjusted to
			// the element rather than to the previous image being shown.
			this.$refs.backgroundImage.src = ''

			let width = this.$refs.backgroundImage.width
			let height = this.$refs.backgroundImage.height

			// Restore the current background so it is shown instead of an empty
			// background while the new one is being generated.
			this.$refs.backgroundImage.src = this.blurredBackgroundImage

			const sourceAspectRatio = this.blurredBackgroundImageSource.width / this.blurredBackgroundImageSource.height
			const canvasAspectRatio = width / height

			if (canvasAspectRatio > sourceAspectRatio) {
				height = width / sourceAspectRatio
			} else if (canvasAspectRatio < sourceAspectRatio) {
				width = height * sourceAspectRatio
			}

			const cacheId = this.backgroundImageUrl + '-' + width + '-' + height + '-' + this.backgroundBlur
			if (this.blurredBackgroundImageCache[cacheId]) {
				this.blurredBackgroundImage = this.blurredBackgroundImageCache[cacheId]

				return
			}

			blur(this.blurredBackgroundImageSource, width, height, this.backgroundBlur).then(image => {
				if (this.isDestroyed) {
					return
				}

				this.blurredBackgroundImage = image
				this.blurredBackgroundImageCache[cacheId] = this.blurredBackgroundImage
			})
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

</style>
