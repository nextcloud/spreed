/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license GNU AGPL version 3 or any later version
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

import debounce from 'debounce'

const video = {

	data() {
		return {
			videoStyle: {},
			incomingStreamAspectRatio: null,
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
	},

	watch: {
		// If the parent aspect ratio changes, we might need to change the video
		// css rules
		videoContainerAspectRatio() {
			debounce(this.getVideoStyle(), 200)
		},
		// If the video stream is enabled, get the style of the video element
		hasVideoStream() {
			this.getVideoStyle()
		},
	},

	methods: {
		// Get the width and height rules for the video element
		getVideoStyle() {
			// Get the incoming video aspect ratio
			this.getIncomingStreamAspectRatio()
			// Compare it with the parent's aspect ratio
			if (this.videoContainerAspectRatio >= this.incomingStreamAspectRatio) {
				this.videoStyle = { width: '100%', height: 'auto' }
			} else {
				this.videoStyle = { width: 'auto', height: '100%' }
			}
		},
		// Get the aspect ratio of the incoming stream
		getIncomingStreamAspectRatio() {
			const incomingStreamWidth = this.$refs.video.videoWidth
			const incomingStreamHeight = this.$refs.video.videoHeight
			this.incomingStreamAspectRatio = incomingStreamWidth / incomingStreamHeight
		},
	},
}

export default video
