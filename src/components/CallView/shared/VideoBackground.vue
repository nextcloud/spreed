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
		<div :style="{'background-color': backgroundColor }"
			class="video-background" />
		<div ref="darkener"
			class="darken" />
	</div>
</template>

<script>
import { average } from 'color.js'
import axios from '@nextcloud/axios'
import usernameToColor from '@nextcloud/vue/dist/Functions/usernameToColor'
import { generateUrl } from '@nextcloud/router'
import { getBuilder } from '@nextcloud/browser-storage'

const browserStorage = getBuilder('nextcloud').persist().build()

// note: this info is shared with the Avatar component
/**
 * @param {string} userId ID of the user
 */
function getUserHasAvatar(userId) {
	const flag = browserStorage.getItem('user-has-avatar.' + userId)
	if (typeof flag === 'string') {
		return Boolean(flag)
	}
	return null
}

/**
 * @param {string} userId ID of the user
 * @param {string} flag The boolean flag as string
 */
function setUserHasAvatar(userId, flag) {
	browserStorage.setItem('user-has-avatar.' + userId, flag)
}

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

	data() {
		return {
			hasPicture: false,
		}
	},

	computed: {
		backgroundImageAverageColor() {
			if (!this.backgroundImageUrl) {
				return ''
			}

			return this.$store.getters.getCachedBackgroundImageAverageColor(this.backgroundImageUrl)
		},
		backgroundColor() {
			if (this.hasPicture) {
				return this.backgroundImageAverageColor
			}

			// If the prop is empty. We're not checking for the default value
			// because the user's displayName might be '?'
			if (!this.displayName) {
				return 'var(--color-text-maxcontrast)'
			} else {
				const color = usernameToColor(this.displayName)
				return `rgb(${color.r}, ${color.g}, ${color.b})`
			}
		},
		backgroundImageUrl() {
			if (!this.user) {
				return null
			}

			return generateUrl(`avatar/${this.user}/64`)
		},
	},

	watch: {
		backgroundImageUrl: {
			immediate: true,
			handler() {
				if (!this.backgroundImageUrl) {
					return
				}

				if (this.backgroundImageAverageColor) {
					// Already calculated, no need to do it again.
					return
				}

				average(this.backgroundImageUrl, { format: 'hex' }).then(color => {
					this.$store.dispatch('setCachedBackgroundImageAverageColor', {
						videoBackgroundId: this.backgroundImageUrl,
						backgroundImageAverageColor: color,
					})
				})
			},
		},
	},

	async beforeMount() {
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
			await axios.get(generateUrl(`avatar/${this.user}/64`))

			this.hasPicture = true
			setUserHasAvatar(this.user, true)
		} catch (exception) {
			console.debug(exception)
		}
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
