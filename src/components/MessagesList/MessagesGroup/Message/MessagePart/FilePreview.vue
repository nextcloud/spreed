<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Grigorii Shartsev <me@shgk.me>
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
	<component :is="filePreviewElement"
		v-bind="filePreviewBinding"
		:tabindex="wrapperTabIndex"
		class="file-preview"
		:class="{ 'file-preview--viewer-available': isViewerAvailable,
			'file-preview--upload-editor': isUploadEditor,
			'file-preview--shared-items-grid': isSharedItems && !rowLayout,
			'file-preview--row-layout': rowLayout }"
		@click.exact="handleClick"
		@keydown.enter="handleClick">
		<span v-if="!isLoading || fallbackLocalUrl"
			class="image-container"
			:class="{'playable': isPlayable}">
			<span v-if="isPlayable && !smallPreview" class="play-video-button">
				<PlayCircleOutline :size="48"
					fill-color="#ffffff" />
			</span>
			<img v-if="!failed"
				v-tooltip="previewTooltip"
				:class="previewImageClass"
				class="file-preview__image"
				alt=""
				:src="previewUrl">
			<img v-else
				:class="previewImageClass"
				alt=""
				:src="defaultIconUrl">
			<NcProgressBar v-if="showUploadProgress"
				class="file-preview__progress"
				type="circular"
				:value="uploadProgress" />
		</span>
		<span v-else-if="isLoading"
			v-tooltip="previewTooltip"
			class="preview loading"
			:style="imageContainerStyle" />
		<NcButton v-if="isUploadEditor"
			class="remove-file"
			tabindex="1"
			type="primary"
			:aria-label="removeAriaLabel"
			@click="$emit('remove-file', id)">
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
import Close from 'vue-material-design-icons/Close.vue'
import PlayCircleOutline from 'vue-material-design-icons/PlayCircleOutline.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { encodePath } from '@nextcloud/paths'
import { generateUrl, imagePath, generateRemoteUrl } from '@nextcloud/router'
import { getUploader } from '@nextcloud/upload'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'

import AudioPlayer from './AudioPlayer.vue'

import { useViewer } from '../../../../../composables/useViewer.js'
import { SHARED_ITEM } from '../../../../../constants.js'
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
		NcProgressBar,
		Close,
		PlayCircleOutline,
		NcButton,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
		/**
		 * File id
		 */
		id: {
			type: String,
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
		 * File name
		 */
		name: {
			type: String,
			required: true,
		},
		/**
		 * File path relative to the user's home storage,
		 * or link share root, includes the file name.
		 */
		path: {
			type: String,
			default: '',
		},
		/**
		 * File size in bytes
		 */
		size: {
			type: String,
			default: '-1',
		},
		/**
		 * Download link
		 */
		link: {
			type: String,
			default: '',
		},
		/**
		 * Mime type
		 */
		mimetype: {
			type: String,
			default: '',
		},
		/**
		 * File ETag
		 */
		etag: {
			type: String,
			default: '',
		},
		/**
		 * File ETag
		 */
		permissions: {
			type: String,
			default: '0',
		},
		/**
		 * Whether a preview is available, string "yes" for yes
		 * otherwise the string "no"
		 */
		// FIXME: use booleans here
		previewAvailable: {
			type: String,
			default: 'no',
		},

		/**
		 * If preview and metadata are available, return width
		 */
		width: {
			type: String,
			default: null,
		},

		/**
		 * If preview and metadata are available, return height
		 */
		height: {
			type: String,
			default: null,
		},

		/**
		 * Whether to render a small preview to embed in replies
		 */
		smallPreview: {
			type: Boolean,
			default: false,
		},
		/**
		 * Upload id from the file upload store.
		 *
		 * In case this component is used to display a file that is being uploaded
		 * this parameter is used to access the file upload status in the store
		 */
		uploadId: {
			type: Number,
			default: null,
		},
		/**
		 * File upload index from the file upload store.
		 *
		 * In case this component is used to display a file that is being uploaded
		 * this parameter is used to access the file upload status in the store
		 */
		index: {
			type: String,
			default: '',
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
		/**
		 * The link to the file for displaying it in the preview
		 */
		localUrl: {
			type: String,
			default: '',
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
				!this.mimetype.startsWith('image/')
				// the image has no preview (ex: disabled on server)
				|| (this.previewAvailable !== 'yes' && !this.localUrl)
				// the preview failed loading
				|| this.failed
				// always show in upload editor
				|| this.isUploadEditor
			)
		},

		fileDetail() {
			return this.name
		},

		fallbackLocalUrl() {
			if (!this.mimetype.startsWith('image/') && !this.mimetype.startsWith('video/')) {
				return undefined
			}
			return this.$store.getters.getLocalUrl(this.referenceId)
		},

		previewTooltip() {
			if (this.shouldShowFileDetail) {
				// no tooltip as the file name is already visible directly
				return null
			}
			return {
				content: this.name,
				delay: { show: 500 },
				placement: 'left',
			}
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
					name: this.name,
					path: this.path,
					link: this.link,
				}
			}
			return {
				href: this.link,
				target: '_blank',
				rel: 'noopener noreferrer',
			}
		},

		defaultIconUrl() {
			return OC.MimeType.getIconUrl(this.mimetype) || imagePath('core', 'filetypes/file')
		},

		mediumPreview() {
			return !this.mimetype.startsWith('image/') && !this.mimetype.startsWith('video/')
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
			} else if (this.previewAvailable === 'yes') {
				classes += 'media'
			}

			return classes
		},

		imageContainerStyle() {
			// Fallback for loading mimeicons (preview for audio files is not provided)
			if (this.previewAvailable !== 'yes' || this.mimetype.startsWith('audio/')) {
				return {
					width: this.smallPreview ? '32px' : '128px',
					height: this.smallPreview ? '32px' : '128px',
				}
			}

			const widthConstraint = this.smallPreview ? 32 : (this.mediumPreview ? 192 : 600)
			const heightConstraint = this.smallPreview ? 32 : (this.mediumPreview ? 192 : 384)

			// Fallback when no metadata available
			if (!this.width || !this.height) {
				return {
					width: widthConstraint + 'px',
					height: heightConstraint + 'px',
				}
			}

			const sizeMultiplicator = Math.min(
				(heightConstraint > parseInt(this.height, 10) ? 1 : (heightConstraint / parseInt(this.height, 10))),
				(widthConstraint > parseInt(this.width, 10) ? 1 : (widthConstraint / parseInt(this.width, 10)))
			)

			return {
				width: parseInt(this.width, 10) * sizeMultiplicator + 'px',
				aspectRatio: this.width + '/' + this.height,
			}
		},

		previewType() {
			if (this.hasTemporaryImageUrl) {
				return PREVIEW_TYPE.TEMPORARY
			}

			if (this.previewAvailable !== 'yes') {
				return PREVIEW_TYPE.MIME_ICON
			}
			const maxGifSize = getCapabilities()?.spreed?.config?.previews?.['max-gif-size'] || 3145728
			if (this.mimetype === 'image/gif' && parseInt(this.size, 10) <= maxGifSize) {
				return PREVIEW_TYPE.DIRECT
			}

			return PREVIEW_TYPE.PREVIEW
		},

		previewUrl() {
			const userId = this.$store.getters.getUserId()

			if (this.previewType === PREVIEW_TYPE.TEMPORARY) {
				return this.localUrl
			}
			if (this.fallbackLocalUrl) {
				return this.fallbackLocalUrl
			}
			if (this.previewType === PREVIEW_TYPE.MIME_ICON || this.rowLayout) {
				return OC.MimeType.getIconUrl(this.mimetype)
			}
			// whether to embed/render the file directly
			if (this.previewType === PREVIEW_TYPE.DIRECT) {
				// return direct image
				if (userId === null) {
					// guest mode, use public link download URL
					return this.link + '/download/' + encodePath(this.name)
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
				const token = this.link.slice(this.link.lastIndexOf('/') + 1)
				return generateUrl('/apps/files_sharing/publicpreview/{token}?x=-1&y={height}&a=1', {
					token,
					height: previewSize,
				})
			} else {
				return generateUrl('/core/preview?fileId={fileId}&x=-1&y={height}&a=1', {
					fileId: this.id,
					height: previewSize,
				})
			}
		},

		isViewerAvailable() {
			if (!OCA.Viewer) {
				return false
			}

			const availableHandlers = OCA.Viewer.availableHandlers
			for (let i = 0; i < availableHandlers.length; i++) {
				if (availableHandlers[i]?.mimes?.includes && availableHandlers[i].mimes.includes(this.mimetype)) {
					return true
				}
			}

			return false
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
			return this.mimetype === 'image/gif' || this.mimetype.startsWith('video/')
		},

		internalAbsolutePath() {
			if (!this.path) {
				return ''
			}
			return this.path.startsWith('/') ? this.path : '/' + this.path
		},

		isTemporaryUpload() {
			return this.id.startsWith('temp') && this.index && this.uploadId
		},

		uploadFile() {
			return this.$store.getters.getUploadFile(this.uploadId, this.index)
		},

		upload() {
			return this.uploadManager?.queue.find(item => item._source.includes(this.uploadFile.sharePath))
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
				&& ['shared', 'sharing', 'successUpload', 'uploading'].includes(this.uploadFile?.status)
		},

		hasTemporaryImageUrl() {
			return this.mimetype.startsWith('image/') && this.localUrl
		},

		wrapperTabIndex() {
			return this.isUploadEditor ? '0' : undefined
		},

		removeAriaLabel() {
			return t('spreed', 'Remove {fileName}', { fileName: this.name })
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

		const img = new Image()
		img.onerror = () => {
			this.isLoading = false
			this.failed = true
		}
		img.onload = () => {
			this.isLoading = false
		}
		img.src = this.previewUrl
	},

	beforeDestroy() {
		this.uploadManager = null
	},

	methods: {
		handleClick(event) {
			if (this.isUploadEditor) {
				this.$emit('remove-file', this.id)
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
					.map(item => item.messageParameters.file)

				// Get available media files from store and put them to the list to navigate through slides
				const mediaFiles = this.sharedItemsStore.sharedItems(this.token).media
				const list = getRevertedList(mediaFiles)
				const loadMore = async () => {
					const { messages } = await this.sharedItemsStore.getSharedItems(this.token, SHARED_ITEM.TYPES.MEDIA)
					return getRevertedList(messages)
				}

				this.openViewer(this.internalAbsolutePath, list, this, loadMore)
			} else {
				this.openViewer(this.internalAbsolutePath, [this], this)

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

		.file-preview__image.media {
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
		right: 0;
		transform: translate(100%, -50%);
	}

	.loading {
		display: inline-block;
		min-width: 32px;
		background-color: var(--color-background-dark);
	}

	.mimeicon {
		min-height: 128px;
	}

	.mimeicon.preview-small {
		min-height: auto;
		height: 32px;
	}

	.preview {
		display: inline-block;
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 384px;
	}

	.preview-medium {
		display: inline-block;
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 192px;
	}

	.preview-small {
		display: inline-block;
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 32px;
	}

	.image-container {
		position: relative;
		display: inline-block;
		height: 100%;

		&.playable {
			.preview {
				transition: filter 250ms ease-in-out;
			}

			.play-video-button {
				position: absolute;
				height: 48px; /* for proper vertical centering */
				top: 50%;
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
		height: 36px;
		border-radius: var(--border-radius);
		padding: 2px 4px;

		.image-container {
			height: 100%;
		}

		.name-container {
			padding: 0 4px;
		}

		.loading {
			width: 36px;
			height: 36px;
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
	right: 8px;
}

</style>
