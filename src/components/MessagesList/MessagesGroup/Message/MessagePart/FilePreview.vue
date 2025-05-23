<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<component :is="filePreviewElement"
		:tabindex="wrapperTabIndex"
		class="file-preview"
		:class="{ 'file-preview--viewer-available': isViewerAvailable,
			'file-preview--upload-editor': isUploadEditor,
			'file-preview--shared-items-grid': isSharedItems && !rowLayout,
			'file-preview--row-layout': rowLayout }"
		v-bind="filePreviewBinding"
		@click.exact="handleClick"
		@keydown.enter="handleClick">
		<span :title="file.name"
			class="image-container"
			:class="{ 'playable': isPlayable }"
			:style="imageContainerStyle">
			<img class="file-preview__image"
				:class="previewImageClass"
				:alt="file.name"
				:src="failed ? defaultIconUrl : previewUrl"
				@load="onLoad"
				@error="onError">
			<template v-if="!isLoading || fallbackLocalUrl">
				<span v-if="isPlayable && !smallPreview" class="play-video-button">
					<PlayCircleOutline :size="48"
						fill-color="#ffffff" />
				</span>
				<NcProgressBar v-if="showUploadProgress"
					class="file-preview__progress"
					type="circular"
					:value="uploadProgress" />
			</template>
			<TransitionWrapper v-else-if="isLoading" name="fade">
				<canvas v-if="file.blurhash"
					ref="blurCanvas"
					width="32"
					height="32"
					class="preview preview-loading" />
				<NcLoadingIcon v-else class="preview preview-loading" />
			</TransitionWrapper>
		</span>

		<NcButton v-if="isUploadEditor"
			class="remove-file"
			tabindex="1"
			type="primary"
			:aria-label="removeAriaLabel"
			@click="$emit('remove-file', file.id)">
			<template #icon>
				<Close />
			</template>
		</NcButton>
		<div v-if="shouldShowFileDetail" class="name-container">
			{{ fileDetail }}
		</div>
	</component>
</template>

<script>
import { decode } from 'blurhash'

import Close from 'vue-material-design-icons/Close.vue'
import PlayCircleOutline from 'vue-material-design-icons/PlayCircleOutline.vue'

import { t } from '@nextcloud/l10n'
import { encodePath } from '@nextcloud/paths'
import { generateUrl, imagePath, generateRemoteUrl } from '@nextcloud/router'
import { getUploader } from '@nextcloud/upload'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'

import AudioPlayer from './AudioPlayer.vue'
import TransitionWrapper from '../../../../UIShared/TransitionWrapper.vue'

import { useViewer } from '../../../../../composables/useViewer.js'
import { SHARED_ITEM } from '../../../../../constants.ts'
import { getTalkConfig } from '../../../../../services/CapabilitiesManager.ts'
import { useSharedItemsStore } from '../../../../../stores/sharedItems.js'

const PREVIEW_TYPE = {
	TEMPORARY: 0,
	MIME_ICON: 1,
	DIRECT: 2,
	PREVIEW: 3,
}

export default {
	name: 'FilePreview',

	components: {
		NcButton,
		NcLoadingIcon,
		NcProgressBar,
		TransitionWrapper,
		// Icons
		Close,
		PlayCircleOutline,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		messageId: {
			type: [String, Number],
			default: 0,
		},

		nextMessageId: {
			type: [String, Number],
			default: 0,
		},

		/**
		 * File object
		 */
		file: {
			type: Object,
			required: true,
		},

		/**
		 * Reference id from the message
		 */
		referenceId: {
			type: String,
			default: '',
		},

		/**
		 * Whether to render a small preview to embed in replies
		 */
		smallPreview: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether the container is the upload editor.
		 * True if this component is used in the upload editor.
		 */
		// FIXME: file-preview should be encapsulated and not be aware of its surroundings
		isUploadEditor: {
			type: Boolean,
			default: false,
		},

		rowLayout: {
			type: Boolean,
			default: false,
		},

		isSharedItems: {
			type: Boolean,
			default: false,
		},

		itemType: {
			type: String,
			default: '',
		},
	},

	emits: ['remove-file'],

	setup() {
		const { openViewer } = useViewer('talk')
		const sharedItemsStore = useSharedItemsStore()

		return {
			openViewer,
			sharedItemsStore,
		}
	},

	data() {
		return {
			isLoading: true,
			failed: false,
			uploadManager: null,
		}
	},

	computed: {
		shouldShowFileDetail() {
			if (this.isSharedItems && !this.rowLayout) {
				return false
			}
			// display the file detail below the preview if the preview
			// is not easily recognizable, when:
			return (
				// the file is not an image
				!this.file.mimetype.startsWith('image/')
				// the image has no preview (ex: disabled on server)
				|| (this.file['preview-available'] !== 'yes' && !this.file.localUrl)
				// the preview failed loading
				|| this.failed
				// always show in upload editor
				|| this.isUploadEditor
			)
		},

		fileDetail() {
			return this.file.name
		},

		fallbackLocalUrl() {
			return this.$store.getters.getLocalUrl(this.referenceId)
		},

		// This is used to decide which outer element type to use
		filePreviewElement() {
			if (this.isUploadEditor || this.isTemporaryUpload) {
				return 'div'
			} else if (this.isVoiceMessage && !this.isSharedItems) {
				return AudioPlayer
			}
			return 'a'
		},

		filePreviewBinding() {
			if (this.isUploadEditor || this.isTemporaryUpload) {
				return
			} else if (this.isVoiceMessage && !this.isSharedItems) {
				return {
					name: this.file.name,
					path: this.file.path,
					link: this.file.link,
					localUrl: this.fallbackLocalUrl,
					messageId: Number(this.messageId),
					nextMessageId: Number(this.nextMessageId),
				}
			}
			return {
				href: this.file.link,
				target: '_blank',
				rel: 'noopener noreferrer',
			}
		},

		defaultIconUrl() {
			return OC.MimeType.getIconUrl(this.file.mimetype) || imagePath('core', 'filetypes/file')
		},

		mediumPreview() {
			return !this.file.mimetype.startsWith('image/') && !this.file.mimetype.startsWith('video/')
		},

		previewImageClass() {
			let classes = ''
			if (this.smallPreview) {
				classes += 'preview-small '
			} else if (this.mediumPreview) {
				classes += 'preview-medium '
			} else {
				classes += 'preview '
			}

			if (this.failed || this.previewType === PREVIEW_TYPE.MIME_ICON || this.rowLayout) {
				classes += 'mimeicon'
			} else if (this.file['preview-available'] === 'yes') {
				classes += 'media'
			}

			return classes
		},

		imageContainerStyle() {
			// Uploaded image in temporary message (use actual image size)
			if (this.previewType === PREVIEW_TYPE.TEMPORARY && !this.isUploadEditor) {
				return {}
			}

			// Fallback for loading mimeicons (preview for audio files is not provided)
			if (this.file['preview-available'] !== 'yes' || this.file.mimetype.startsWith('audio/') || this.failed) {
				return {
					width: this.smallPreview ? '32px' : '128px',
					height: this.smallPreview ? '32px' : '128px',
				}
			}

			const widthConstraint = this.smallPreview ? 32 : (this.mediumPreview ? 192 : 600)
			const heightConstraint = this.smallPreview ? 32 : (this.mediumPreview ? 192 : 384)

			// Actual size when no metadata available
			if (!this.file.width || !this.file.height) {
				return {}
			}

			const sizeMultiplicator = Math.min(
				(heightConstraint > parseInt(this.file.height, 10) ? 1 : (heightConstraint / parseInt(this.file.height, 10))),
				(widthConstraint > parseInt(this.file.width, 10) ? 1 : (widthConstraint / parseInt(this.file.width, 10))),
			)

			return {
				width: parseInt(this.file.width, 10) * sizeMultiplicator + 'px',
				aspectRatio: this.file.width + '/' + this.file.height,
			}
		},

		maxGifSize() {
			return getTalkConfig(this.token, 'previews', 'max-gif-size') || 3145728
		},

		previewType() {
			if (this.hasTemporaryImageUrl) {
				return PREVIEW_TYPE.TEMPORARY
			}

			if (this.file['preview-available'] !== 'yes') {
				return PREVIEW_TYPE.MIME_ICON
			}
			if (this.file.mimetype === 'image/gif' && parseInt(this.file.size, 10) <= this.maxGifSize) {
				return PREVIEW_TYPE.DIRECT
			}

			return PREVIEW_TYPE.PREVIEW
		},

		previewUrl() {
			const userId = this.$store.getters.getUserId()

			if (this.previewType === PREVIEW_TYPE.TEMPORARY) {
				return this.file.localUrl
			}
			if (this.fallbackLocalUrl) {
				return this.fallbackLocalUrl
			}
			if (this.previewType === PREVIEW_TYPE.MIME_ICON || this.rowLayout) {
				return OC.MimeType.getIconUrl(this.file.mimetype)
			}
			// whether to embed/render the file directly
			if (this.previewType === PREVIEW_TYPE.DIRECT) {
				// return direct image
				if (userId === null) {
					// guest mode, use public link download URL
					return this.file.link + '/download/' + encodePath(this.file.name)
				} else {
					// use direct DAV URL
					return generateRemoteUrl(`dav/files/${userId}`) + encodePath(this.internalAbsolutePath)
				}
			}

			// use preview provider URL to render a smaller preview
			let previewSize = 384
			if (this.smallPreview) {
				previewSize = 32
			}
			previewSize = Math.ceil(previewSize * window.devicePixelRatio)
			if (userId === null) {
				// guest mode: grab token from the link URL
				// FIXME: use a cleaner way...
				const token = this.file.link.slice(this.file.link.lastIndexOf('/') + 1)
				return generateUrl('/apps/files_sharing/publicpreview/{token}?x=-1&y={height}&a=1', {
					token,
					height: previewSize,
				})
			} else {
				return generateUrl('/core/preview?fileId={fileId}&x=-1&y={height}&a=1', {
					fileId: this.file.id,
					height: previewSize,
				})
			}
		},

		isViewerAvailable() {
			return OCA.Viewer?.mimetypes?.includes(this.file.mimetype)
		},

		isVoiceMessage() {
			return this.itemType === SHARED_ITEM.TYPES.VOICE
		},

		isPlayable() {
			// don't show play button for direct renders
			if (this.failed || !this.isViewerAvailable || this.previewType !== PREVIEW_TYPE.PREVIEW) {
				return false
			}

			// videos only display a preview, so always show a button if playable
			return this.file.mimetype === 'image/gif' || this.file.mimetype.startsWith('video/')
		},

		internalAbsolutePath() {
			if (!this.file.path) {
				return ''
			}
			return this.file.path.startsWith('/') ? this.file.path : '/' + this.file.path
		},

		isTemporaryUpload() {
			return this.file.id.startsWith('temp') && this.file.index && this.file.uploadId
		},

		uploadFile() {
			return this.$store.getters.getUploadFile(this.file.uploadId, this.file.index)
		},

		upload() {
			return this.uploadManager?.queue.find((item) => item._source.includes(this.uploadFile?.sharePath))
		},

		uploadProgress() {
			switch (this.uploadFile?.status) {
				case 'shared':
				case 'sharing':
				case 'successUpload':
					return 100
				case 'uploading':
					return this.upload
						? this.upload._uploaded / this.upload._size * 100
						: 100 // file was removed from the upload queue, so considering done
				case 'pendingUpload':
				case 'initialised':
				default:
					return 0
			}
		},

		showUploadProgress() {
			return this.isTemporaryUpload && !this.isUploadEditor
				&& ['shared', 'sharing', 'successUpload', 'uploading', 'failedUpload'].includes(this.uploadFile?.status)
		},

		hasTemporaryImageUrl() {
			return SHARED_ITEM.MEDIA_ALLOWED_PREVIEW.includes(this.file.mimetype) && this.file.localUrl
		},

		wrapperTabIndex() {
			return this.isUploadEditor ? '0' : undefined
		},

		removeAriaLabel() {
			return t('spreed', 'Remove {fileName}', { fileName: this.file.name })
		},
	},

	watch: {
		uploadProgress(value) {
			if (value === 100) {
				this.uploadManager = null
			}
		},
	},

	mounted() {
		if (this.isTemporaryUpload && !this.isUploadEditor) {
			this.uploadManager = getUploader()
		}

		if (this.file.blurhash && this.file.width && this.file.height) {
			const ctx = this.$refs.blurCanvas.getContext('2d')
			const imageData = ctx.createImageData(32, 32)
			imageData.data.set(decode(this.file.blurhash, 32, 32))
			ctx.putImageData(imageData, 0, 0)
		}
	},

	beforeDestroy() {
		this.uploadManager = null
	},

	methods: {
		t,

		onLoad() {
			this.isLoading = false
		},

		onError() {
			this.isLoading = false
			this.failed = true
		},

		handleClick(event) {
			if (this.isUploadEditor) {
				this.$emit('remove-file', this.file.id)
				return
			}

			if (this.previewType === PREVIEW_TYPE.TEMPORARY) {
				// File is not yet uploaded, so no Viewer available
				return
			}

			if (!this.isViewerAvailable) {
				// Regular event handling by opening the link.
				return
			}

			event.stopPropagation()
			event.preventDefault()

			if (this.itemType === SHARED_ITEM.TYPES.MEDIA) {
				const getRevertedList = (items) => Object.values(items).reverse()
					.map((item) => item.messageParameters.file)

				// Get available media files from store and put them to the list to navigate through slides
				const mediaFiles = this.sharedItemsStore.sharedItems(this.token).media
				const list = getRevertedList(mediaFiles)
				const loadMore = async () => {
					const { messages } = await this.sharedItemsStore.getSharedItems(this.token, SHARED_ITEM.TYPES.MEDIA)
					return getRevertedList(messages)
				}

				this.openViewer(this.internalAbsolutePath, list, this.file, loadMore)
			} else {
				this.openViewer(this.internalAbsolutePath, [this.file], this.file)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.file-preview {
	position: relative;
	min-width: 0;
	max-width: 100%;
	display: inline-block;

	border-radius: 16px;

	box-sizing: content-box !important;

	&:hover,
	&:focus,
	&:focus-visible {
		background-color: var(--color-background-hover);
		outline: none;

		.remove-file {
			visibility: visible;
		}

		.image-container:has(.file-preview__image.media) {
			outline: 2px solid var(--color-primary-element);
		}
	}

	&__image {
		object-fit: cover;
		transition: outline 0.1s ease-in-out;
	}

	&__progress {
		position: absolute;
		top: 50%;
		inset-inline-end: calc(var(--progress-bar-height) * -1);
		transform: translateY(-50%);
	}

	.mimeicon {
		min-height: 128px;
	}

	.mimeicon.preview-small {
		min-height: auto;
		height: 32px;
	}

	.preview {
		border-radius: var(--border-radius);
		max-width: min(100%, 600px);
		max-height: 384px;
	}

	.preview-medium {
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 192px;
	}

	.preview-small {
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 32px;
	}

	.preview-loading {
		position: absolute;
		border-radius: var(--border-radius);
		width: 100%;
		height: 100%;
		background-color: var(--color-background-dark);
	}

	.image-container {
		position: relative;
		display: inline-flex;
		width: 100%;
		height: 100%;
		max-width: 100%;
		max-height: 100%;
		border-radius: var(--border-radius);

		&.playable {
			.preview {
				transition: filter 250ms ease-in-out;
			}

			.play-video-button {
				position: absolute;
				height: 48px; /* for proper vertical centering */
				top: 50%;
				/* stylelint-disable-next-line csstools/use-logical */
				left: 50%;
				transform: translate(-50%, -50%);
				opacity: 0.8;
				z-index: 1;
				transition: opacity 250ms ease-in-out;
			}

			&:hover {
				.preview {
					filter: brightness(80%);
				}

				.play-video-button {
					opacity: 1;
				}
			}
		}
	}

	.name-container {
		font-weight: bold;
		width: 100%;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	&:not(.file-preview--viewer-available) {
		strong:after {
			content: ' ↗';
		}
	}

	&--upload-editor {
		max-width: 140px;
		max-height: 140px;
		padding: 12px 12px 24px 12px;
		margin: 10px;

		.preview {
			margin: auto;
			width: 128px;
			height: 128px;
		}

		.loading {
			width: 100%;
		}
	}

	&--row-layout {
		display: flex;
		align-items: center;
		height: 32px;
		border-radius: var(--border-radius);
		padding: 2px 4px;

		.image-container {
			flex-shrink: 0;
			height: 32px;
			width: 32px;
		}

		.name-container {
			padding: 0 4px;
		}

		.loading {
			width: 32px;
			height: 32px;
		}
	}

	&--shared-items-grid {
		aspect-ratio: 1;

		.preview {
			width: 100%;
			min-height: unset;
			height: 100% !important;
		}
	}
}

.remove-file {
	visibility: hidden;
	position: absolute !important;
	top: 8px;
	inset-inline-end: 8px;
}

</style>
