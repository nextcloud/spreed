<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="matrix-media" :class="{ 'matrix-media--card': !isImage && !isVideo && !isAudio }">
		<a
			v-if="isImage"
			:href="link"
			target="_blank"
			rel="noopener noreferrer"
			class="matrix-media__image-link"
			:title="name">
			<img
				:src="thumbnail || link"
				:alt="name"
				:width="width || undefined"
				:height="height || undefined"
				class="matrix-media__image"
				loading="lazy"
				@error="onImageError">
		</a>
		<video
			v-else-if="isVideo"
			:src="link"
			controls
			preload="metadata"
			class="matrix-media__video" />
		<audio
			v-else-if="isAudio"
			:src="link"
			controls
			preload="metadata"
			class="matrix-media__audio" />
		<a
			v-else
			:href="link"
			target="_blank"
			rel="noopener noreferrer"
			class="matrix-media__file"
			:title="name">
			<IconFileOutline :size="24" />
			<span class="matrix-media__file-name">{{ name }}</span>
			<span v-if="sizeLabel" class="matrix-media__file-size">{{ sizeLabel }}</span>
		</a>
		<div class="matrix-media__actions">
			<span v-if="encrypted === '1'" class="matrix-media__encrypted" :title="t('spreed', 'Encrypted attachment, decrypted by this server')">
				<IconLockOutline :size="14" />
			</span>
			<NcButton
				variant="tertiary"
				:disabled="saving"
				:title="t('spreed', 'Save to Nextcloud')"
				:aria-label="t('spreed', 'Save to Nextcloud')"
				@click="saveToNextcloud">
				<template #icon>
					<IconCloudDownloadOutline :size="18" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconCloudDownloadOutline from 'vue-material-design-icons/CloudDownloadOutline.vue'
import IconFileOutline from 'vue-material-design-icons/FileOutline.vue'
import IconLockOutline from 'vue-material-design-icons/LockOutline.vue'

export default {
	name: 'MatrixMedia',

	components: {
		IconCloudDownloadOutline,
		IconFileOutline,
		IconLockOutline,
		NcButton,
	},

	props: {
		id: { type: String, required: true },
		name: { type: String, default: '' },
		mimetype: { type: String, default: 'application/octet-stream' },
		size: { type: [String, Number], default: 0 },
		msgtype: { type: String, default: 'm.file' },
		link: { type: String, default: '' },
		thumbnail: { type: String, default: '' },
		width: { type: [String, Number], default: 0 },
		height: { type: [String, Number], default: 0 },
		encrypted: { type: String, default: '0' },
	},

	setup() {
		return { t }
	},

	data() {
		return { saving: false, imageFailed: false }
	},

	computed: {
		isImage() {
			return !this.imageFailed && this.link && (this.msgtype === 'm.image' || this.mimetype.startsWith('image/'))
		},

		isVideo() {
			return this.link && (this.msgtype === 'm.video' || this.mimetype.startsWith('video/'))
		},

		isAudio() {
			return this.link && (this.msgtype === 'm.audio' || this.mimetype.startsWith('audio/'))
		},

		sizeLabel() {
			const size = parseInt(this.size, 10)
			return size > 0 ? formatFileSize(size) : ''
		},
	},

	methods: {
		onImageError() {
			this.imageFailed = true
		},

		async saveToNextcloud() {
			this.saving = true
			try {
				const response = await axios.post(generateUrl('/apps/spreed/matrix/media/{eventId}/save', { eventId: this.id }), { folder: 'Talk' })
				showSuccess(t('spreed', 'Saved as {path}', { path: response.data.path }))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not save the file'))
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.matrix-media {
	display: inline-flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	max-width: 100%;
	margin: var(--default-grid-baseline) 0;

	&--card {
		flex-direction: row;
		align-items: center;
	}

	&__image {
		display: block;
		max-width: min(100%, 480px);
		max-height: 320px;
		width: auto;
		height: auto;
		border-radius: var(--border-radius-large);
		object-fit: contain;
	}

	&__video {
		max-width: min(100%, 480px);
		max-height: 320px;
		border-radius: var(--border-radius-large);
	}

	&__audio {
		max-width: 100%;
	}

	&__file {
		display: inline-flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-hover);
		max-width: 100%;

		&:hover {
			background-color: var(--color-background-dark);
		}
	}

	&__file-name {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__file-size {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small);
		white-space: nowrap;
	}

	&__actions {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
	}

	&__encrypted {
		display: inline-flex;
		color: var(--color-text-maxcontrast);
	}
}
</style>
