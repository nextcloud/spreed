<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<a :href="link"
		class="container"
		:class="{ 'is-viewer-available': isViewerAvailable }"
		target="_blank"
		rel="noopener noreferrer"
		@click="showPreview">
		<img v-if="!isLoading && !failed"
			:class="previewSizeClass"
			alt=""
			:src="previewUrl">
		<img v-if="!isLoading && failed"
			:class="previewSizeClass"
			alt=""
			:src="defaultIconUrl">
		<span v-if="isLoading"
			class="preview loading" />
		<strong>{{ name }}</strong>
	</a>
</template>

<script>
import { generateUrl, imagePath } from '@nextcloud/router'

export default {
	name: 'FilePreview',
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
			required: true,
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
			default: 128,
		},
	},
	data() {
		return {
			isLoading: true,
			failed: false,
		}
	},
	computed: {
		defaultIconUrl() {
			return imagePath('core', 'filetypes/file')
		},
		previewSizeClass() {
			if (this.previewSize === 64) {
				return 'preview-64'
			}
			return 'preview'
		},
		previewUrl() {
			if (this.previewAvailable !== 'yes' || this.$store.getters.getUserId() === null) {
				return OC.MimeType.getIconUrl(this.mimetype)
			}

			const previewSize = Math.ceil(this.previewSize * window.devicePixelRatio)
			return generateUrl('/core/preview?fileId={fileId}&x={width}&y={height}', {
				fileId: this.id,
				width: previewSize,
				height: previewSize,
			})
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
		showPreview(event) {
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

.container {
	/* The file preview can not be a block; otherwise it would fill the whole
	width of the container and the loading icon would not be centered on the
	image. */
	display: inline-block;

	/* Show a hover colour around the preview when navigating with the
	 * keyboard through the file links (or hovering them with the mouse). */
	&:hover,
	&:focus,
	&:active {
		.preview {
			background-color: var(--color-background-hover);

			/* Trick to keep the same position while adding a padding to show
			 * the background. */
			box-sizing: content-box !important;
			padding: 10px;
			margin: -10px;
		}
	}

	.preview {
		display: block;
		width: 128px;
		height: 128px;
	}
	.preview-64 {
		display: block;
		width: 64px;
		height: 64px;
	}

	strong {
		/* As the file preview is an inline block the name is set as a block to
		force it to be on its own line below the preview. */
		display: block;
	}

	&:not(.is-viewer-available) {
		strong:after {
			content: ' ↗';
		}
	}
}

</style>
