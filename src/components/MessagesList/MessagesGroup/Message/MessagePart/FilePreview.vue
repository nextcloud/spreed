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
		target="_blank"
		rel="noopener noreferrer">
		<img v-if="!isLoading && !failed"
			class="preview"
			alt=""
			:src="previewUrl">
		<img v-if="!isLoading && failed"
			class="preview"
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
		previewUrl() {
			if (this.previewAvailable !== 'yes' || this.$store.getters.getUserId() === null) {
				return OC.MimeType.getIconUrl(this.mimetype)
			}

			const previewSize = Math.ceil(128 * window.devicePixelRatio)
			return generateUrl('/core/preview?fileId={fileId}&x={width}&y={height}', {
				fileId: this.id,
				width: previewSize,
				height: previewSize,
			})
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
}
</script>

<style lang="scss" scoped>

.container {
	/* The file preview can not be a block; otherwise it would fill the whole
	width of the container and the loading icon would not be centered on the
	image. */
	display: inline-block;

	.preview {
		display: block;
		width: 128px;
		height: 128px;
	}

	strong {
		/* As the file preview is an inline block the name is set as a block to
		force it to be on its own line below the preview. */
		display: block;
	}
}

</style>
