<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<audio ref="audioPlayer"
		class="audio-player"
		controls
		:src="fileURL">
		{{ t('spreed', 'Your browser does not support playing audio files') }}
	</audio>
</template>

<script>
import { t } from '@nextcloud/l10n'
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
		/**
		 * File ID.
		 */
		id: {
			type: Number,
			default: 0,
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

	mounted() {
		document.addEventListener('audioPlayerEnded', (e) => {
			if (!this.$refs.audioPlayer) {
				return
			}

			const voiceMessages = this.$store.getters.getVoiceMessages()

			const previouslyPlayedVoiceMessage = voiceMessages[e.detail.id]
			const thisVoiceMessage = voiceMessages[previouslyPlayedVoiceMessage.nextId]

			if (previouslyPlayedVoiceMessage.nextId === this.id && thisVoiceMessage && thisVoiceMessage.since === 0) {
				this.$refs.audioPlayer.play()
			}
		})

		this.$refs.audioPlayer.addEventListener('ended', (e) => {
			e.target.dispatchEvent(
				new CustomEvent(
					'audioPlayerEnded',
					{
						bubbles: true,
						detail: {
							id: this.id,
						},
					}
				)
			)
		})
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>

.audio-player {
	margin: 12px 0;
	width: 100%;
}
</style>
