/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

const video = {

	data() {
		return {
			incomingStreamAspectRatio: null,
			mouseover: false,
		}
	},
	props: {
		isGrid: {
			type: Boolean,
			default: false,
		},
		showVideoOverlay: {
			type: Boolean,
			default: true,
		},
		videoContainerAspectRatio: {
			type: Number,
		},
		fitVideo: {
			type: Boolean,
			default: false,
		},
		// True when this component is used in the big video slot in the
		// promoted view
		isBig: {
			type: Boolean,
			default: false,
		},
	},

	methods: {
		showShadow() {
			if (this.isSelectable || this.mouseover) {
				this.mouseover = true
			}
		},
		hideShadow() {
			if (this.isSelectable || this.mouseover) {
				this.mouseover = false
			}
		},

		handleClickVideo(e) {
			// Prevent clicks on the media controls buttons to trigger a video selection
			if (e.target.localName === 'button') {
				return
			}
			// Prevent clicks on the "settings icon" of the popover/actions menu to trigger a video selection
			if (e.target.localName === 'svg') {
				return
			}
			this.$emit('click-video')
		},
	},

	computed: {
		videoClass() {
			if (this.fitVideo) {
				return 'video--fit'
			} else {
				return 'video--fill'
			}
		},
	},
}

export default video
