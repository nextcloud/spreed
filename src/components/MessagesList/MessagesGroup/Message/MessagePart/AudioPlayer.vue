<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license AGPL-3.0-or-later
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
	<audio class="audio-player"
		controls
		:src="fileURL">
		{{ t('spreed', 'Your browser does not support playing audio files') }}
	</audio>
</template>

<script>
import { encodePath } from '@nextcloud/paths'
import { generateRemoteUrl } from '@nextcloud/router'

export default {
	name: 'AudioPlayer',

	props: {
		/**
		 * File name
		 */
		name: {
			type: String,
			required: true,
		},

		link: {
			type: String,
			default: '',
		},
		/**
		 * Link share root, includes the file name.
		 */
		path: {
			type: String,
			default: '',
		},
		/**
		 * File path relative to the user's home storage, used for previewing
		 * the audio before upload
		 */
		localUrl: {
			type: String,
			default: '',
		},
	},

	computed: {
		internalAbsolutePath() {
			if (this.path.startsWith('/')) {
				return this.path
			}
			return '/' + this.path
		},

		fileURL() {
			if (this.localUrl) {
				return this.localUrl
			}
			const userId = this.$store.getters.getUserId()
			if (userId === null) {
				// guest mode, use public link download URL
				return this.link + '/download/' + encodePath(this.name)
			} else {
				// use direct DAV URL
				return generateRemoteUrl(`dav/files/${userId}`) + encodePath(this.internalAbsolutePath)
			}
		},
	},

}
</script>

<style lang="scss" scoped>

.audio-player {
	margin: 12px 0;
	width: 100%;
}
</style>
