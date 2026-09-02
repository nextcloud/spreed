<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="audio-player">
		<audio
			ref="audioPlayer"
			class="audio-player__audio"
			controls
			:src="fileURL"
			@ended="handleEnded">
			{{ t('spreed', 'Your browser does not support playing audio files') }}
		</audio>
		<span v-if="showFileName" class="audio-player__name" :title="sanitizedFileName">
			<span class="audio-player__basename">{{ fileNameWithoutExtension }}</span><span v-if="fileExtension">{{ fileExtension }}</span>
		</span>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { encodePath } from '@nextcloud/paths'
import { generateRemoteUrl } from '@nextcloud/router'
import { inject } from 'vue'
import { EventBus } from '../../../../../services/EventBus.ts'
import { useActorStore } from '../../../../../stores/actor.ts'
import { getFileExtension, sanitizeFileName } from '../../../../../utils/fileUpload.ts'

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
		 * Message ID.
		 */
		messageId: {
			type: Number,
			default: 0,
		},

		showFileName: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return {
			actorStore: useActorStore(),
			// Id of the next message in the list, to auto play the next audio message
			nextMessageId: inject('message:nextMessageId', 0),
		}
	},

	computed: {
		sanitizedFileName() {
			return sanitizeFileName(this.name)
		},

		fileNameWithoutExtension() {
			return this.name.slice(0, this.name.length - getFileExtension(this.name).length)
		},

		fileExtension() {
			return getFileExtension(this.name)
		},

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
			const userId = this.actorStore.userId
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
		EventBus.on('audio-player-ended', this.autoPlay)
	},

	beforeUnmount() {
		EventBus.off('audio-player-ended', this.autoPlay)
	},

	methods: {
		t,

		handleEnded() {
			if (!this.nextMessageId) {
				return
			}

			EventBus.emit('audio-player-ended', this.nextMessageId)
		},

		/**
		 * Autoplay the audio message as soon as previous one was played
		 *
		 * @param {number} messageId Message ID to play.
		 */
		autoPlay(messageId) {
			if (messageId !== this.messageId) {
				return
			}

			this.$refs.audioPlayer?.play()
		},
	},
}
</script>

<style lang="scss" scoped>

.audio-player {
	margin: 12px 0;

	&__name {
		display: block;
		max-width: 100%;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
		font-weight: bold;
	}

	&__basename {
		unicode-bidi: isolate;
	}

	&__audio {
		display: block;
	}
}
</style>
