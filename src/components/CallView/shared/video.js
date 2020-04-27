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
		showBottomBar: {
			type: Boolean,
			default: true,
		},
		videoContainerAspectRatio: {
			type: Number,
		},
	},

	created() {
		this.getIncomingStreamAspectRatio()
	},

	watch: {
		videoContainerAspectRatio() {
			this.getVideoStyle()
		},
	},

	methods: {
		getVideoStyle() {
			if (this.videoContainerAspectRatio >= this.incomingStreamAspectRatio) {
				this.pictureRules = { width: '100%', height: 'auto' }
			} else {
				this.pictureRules = { width: 'auto', height: '100%' }
			}
		},
		getIncomingStreamAspectRatio() {
			const incomingStreamWidth = this.$refs.video.videoWidth
			const incomingStreamHeight = this.$refs.video.videoHeight
			this.incomingStreamAspectRatio = incomingStreamWidth / incomingStreamHeight
		},
	},
}

export default video
