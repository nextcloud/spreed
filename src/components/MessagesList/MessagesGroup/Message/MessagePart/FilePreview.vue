<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<file-preview v-bind="filePreview"
		:tabindex="wrapperTabIndex"
		class="file-preview"
		:class="{ 'file-preview--viewer-available': isViewerAvailable, 'file-preview--upload-editor': isUploadEditor }"
		@click="handleClick"
		@keydown.enter="handleClick">
		<img v-if="(!isLoading && !failed)"
			v-tooltip.auto="previewTooltip"
			:class="previewImageClass"
			class="file-preview__image"
			alt=""
			:src="previewUrl">
		<img v-if="!isLoading && failed"
			v-tooltip.auto="previewTooltip"
			:class="previewImageClass"
			alt=""
			:src="defaultIconUrl">
		<span v-if="isLoading"
			v-tooltip.auto="previewTooltip"
			class="preview loading" />
		<strong v-if="isUploadEditor">{{ name }}</strong>
		<button v-if="isUploadEditor"
			tabindex="1"
			:aria-label="removeAriaLabel"
			class="remove-file primary">
			<Close class="remove-file__icon" decorative @click="$emit('remove-file', id)" />
		</button>
		<ProgressBar v-if="isTemporaryUpload && !isUploadEditor" :value="uploadProgress" />
	</file-preview>
</template>

<script>
import { generateUrl, imagePath, generateRemoteUrl } from '@nextcloud/router'
import ProgressBar from '@nextcloud/vue/dist/Components/ProgressBar'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import Close from 'vue-material-design-icons/Close'
import { getCapabilities } from '@nextcloud/capabilities'

const PREVIEW_TYPE = {
	TEMPORARY: 0,
	MIME_ICON: 1,
	DIRECT: 2,
	PREVIEW: 3,
}

export default {
	name: 'FilePreview',

	components: {
		ProgressBar,
		Close,
	},

	directives: {
		tooltip: Tooltip,
	},

	props: {
		type: {
			type: String,
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		path: {
			type: String,
			default: '',
		},
		size: {
			type: Number,
			default: -1,
		},
		link: {
			type: String,
			default: '',
		},
		mimetype: {
			type: String,
			default: '',
		},
		previewAvailable: {
			type: String,
			default: 'no',
		},
		previewSize: {
			type: Number,
			default: 384,
		},
		// In case this component is used to display a file that is being uploaded
		// this parameter is used to access the file upload status in the store
		uploadId: {
			type: Number,
			default: null,
		},
		// In case this component is used to display a file that is being uploaded
		// this parameter is used to access the file upload status in the store
		index: {
			type: String,
			default: '',
		},
		// True if this component is used in the upload editor
		// FIXME: file-preview should be encapsulated and not be aware of its surroundings
		isUploadEditor: {
			type: Boolean,
			default: false,
		},
		// The link to the file for displaying it in the preview
		localUrl: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			isLoading: true,
			failed: false,
		}
	},
	computed: {
		previewTooltip() {
			if (this.isUploadEditor) {
				// no tooltip as the file name is already visible directly
				return null
			}
			return this.name
		},
		// This is used to decide which outer element type to use
		// a or div
		filePreview() {
			if (this.isUploadEditor || this.isTemporaryUpload) {
				return {
					is: 'div',
					tag: 'div',
				}
			}
			return {
				is: 'a',
				tag: 'a',
				href: this.link,
				target: '_blank',
				rel: 'noopener noreferrer',
			}
		},
		defaultIconUrl() {
			return imagePath('core', 'filetypes/file')
		},
		previewImageClass() {
			let classes = ''
			if (this.previewSize === 64) {
				classes += 'preview-64 '
			} else {
				classes += 'preview '
			}

			if (this.failed || this.previewType === PREVIEW_TYPE.MIME_ICON) {
				classes += 'mimeicon'
			}
			return classes
		},
		previewType() {
			if (this.hasTemporaryImageUrl) {
				return PREVIEW_TYPE.TEMPORARY
			}

			if (this.previewAvailable !== 'yes') {
				return PREVIEW_TYPE.MIME_ICON
			}

			const maxGifSize = getCapabilities()?.caps?.spreed?.config?.previews?.['max-gif-size'] || 3145728
			if (this.mimetype === 'image/gif' && this.size <= maxGifSize) {
				return PREVIEW_TYPE.DIRECT
			}

			return PREVIEW_TYPE.PREVIEW
		},
		previewUrl() {
			const userId = this.$store.getters.getUserId()

			if (this.previewType === PREVIEW_TYPE.TEMPORARY) {
				return this.localUrl
			}
			if (this.previewType === PREVIEW_TYPE.MIME_ICON) {
				return OC.MimeType.getIconUrl(this.mimetype)
			}
			// whether to embed/render the file directly
			if (this.previewType === PREVIEW_TYPE.DIRECT) {
				// return direct image
				if (userId === null) {
					// guest mode, use public link download URL
					return this.link + '/download/' + this.name
				} else {
					// use direct DAV URL
					return generateRemoteUrl(`dav/files/${userId}`) + this.internalAbsolutePath
				}
			}

			// use preview provider URL to render a smaller preview
			const previewSize = Math.ceil(this.previewSize * window.devicePixelRatio)
			if (userId === null) {
				// guest mode: grab token from the link URL
				// FIXME: use a cleaner way...
				const token = this.link.substr(this.link.lastIndexOf('/') + 1)
				return generateUrl('/apps/files_sharing/publicpreview/{token}?x=-1&y={height}&a=1', {
					token: token,
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
				if (availableHandlers[i].mimes.includes(this.mimetype)) {
					return true
				}
			}

			return false
		},
		internalAbsolutePath() {
			if (this.path.startsWith('/')) {
				return this.path
			}

			return '/' + this.path
		},
		isTemporaryUpload() {
			return this.id.startsWith('temp') && this.index && this.uploadId
		},
		uploadProgress() {
			if (this.isTemporaryUpload) {
				if (this.$store.getters.uploadProgress(this.uploadId, this.index)) {
					return this.$store.getters.uploadProgress(this.uploadId, this.index)
				}
			}
			return 0
		},
		hasTemporaryImageUrl() {
			return this.mimetype.startsWith('image/') && this.localUrl
		},

		wrapperTabIndex() {
			return this.isUploadEditor ? '0' : ''
		},

		removeAriaLabel() {
			return t('spreed', 'Remove' + this.name)
		},
	},
	mounted() {
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
	methods: {
		handleClick(event) {
			if (this.isUploadEditor) {
				this.$emit('remove-file', this.id)
				return
			}

			if (!this.isViewerAvailable) {
				// Regular event handling by opening the link.
				return
			}

			event.stopPropagation()
			event.preventDefault()

			// The Viewer expects a file to be set in the sidebar if the sidebar
			// is open.
			if (this.$store.getters.getSidebarStatus) {
				OCA.Files.Sidebar.state.file = this.internalAbsolutePath
			}

			OCA.Viewer.open({
				// Viewer expects an internal absolute path starting with "/".
				path: this.internalAbsolutePath,
				list: [
					{
						fileid: parseInt(this.id, 10),
						filename: this.internalAbsolutePath,
						basename: this.name,
						mime: this.mimetype,
						hasPreview: this.previewAvailable === 'yes',
					},
				],
			})
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../../../assets/variables.scss';

.file-preview {
	position: relative;
	width: 100%;
	/* The file preview can not be a block; otherwise it would fill the whole
	width of the container and the loading icon would not be centered on the
	image. */
	display: inline-block;

	border-radius: 16px;

	&:hover,
	&:focus {
		background-color: var(--color-background-hover);
		/* Trick to keep the same position while adding a padding to show
			* the background. */
		box-sizing: content-box !important;
		.remove-file {
			visibility: visible;
		}
	}

	&__image {
		object-fit: cover;
	}

	.loading {
		display: inline-block;
		width: 100%;
	}

	.preview {
		display: inline-block;
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 384px;
	}
	.preview-64 {
		display: inline-block;
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 64px;
	}

	.mimeicon {
		min-height: 128px;
	}

	strong {
		/* As the file preview is an inline block the name is set as a block to
		force it to be on its own line below the preview. */
		display: block;
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
		max-width: 160px;
		max-height: 160px;
		margin: 10px;
		padding: 12px;
		.preview {
			margin: auto;
			width: 128px;
			height: 128px;
		}
		.loading {
			width: 100%;
		}
	}
}

.remove-file {
	visibility: hidden;
	position: absolute;
	top: 8px;
	right: 8px;
	box-shadow: 0 0 4px var(--color-box-shadow);
	width: $clickable-area;
	height: $clickable-area;
	padding: 0;
	margin: 0;
	&__icon {
		color: var(--color-primary-text);
	}
}

</style>
