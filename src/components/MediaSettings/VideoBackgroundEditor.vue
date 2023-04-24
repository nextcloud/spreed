<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="background-editor">
		<template v-if="!showCustomBackgroundPage">
			<button key="clear"
				class="background-editor__element"
				:class="{'background-editor__element--selected': selectedBackground === 'clear'}"
				@click="handleSelectBackground('clear')">
				<Cancel :size="20" />
				{{ t('spreed', 'Clear') }}
			</button>
			<button key="blur"
				:disabled="!blurPreviewAvailable"
				class="background-editor__element"
				:class="{'background-editor__element--selected': selectedBackground === 'blur'}"
				@click="handleSelectBackground('blur')">
				<Blur :size="20" />
				{{ t('spreed', 'Blur') }}
			</button>
			<!-- hide custom background for now -->
			<button key="upload"
				class="background-editor__element"
				@click="showCustomBackgroundPage = true">
				<ImagePlus :size="20" />
				{{ t('spreed', 'Custom') }}
			</button>
			<button v-for="path in backgrounds"
				:key="path"
				aria-label="TODO: add image names as aria labels"
				class="background-editor__element"
				:class="{'background-editor__element--selected': selectedBackground === path}"
				:style="{
					'background-image': 'url(' + path + ')'
				}"
				@click="handleSelectBackground(path)">
				<CheckBold v-if="selectedBackground === path"
					:size="40"
					fill-color="#fff" />
			</button>
		</template>
		<template v-else>
			<button key="clear"
				class="background-editor__element"
				@click="showCustomBackgroundPage = false">
				<ArrowLeft :size="20" />
				{{ t('spreed', 'Back') }}
			</button>
			<button class="background-editor__element"
				@click="clickImportInput">
				<Upload :size="20" />
				{{ t('spreed', 'Upload') }}
			</button>
			<button class="background-editor__element"
				@click="openPicker">
				<Folder :size="20" />
				{{ t('spreed', 'Choose from files') }}
			</button>
		</template>

		<!--native file picker, hidden -->
		<input v-show="false"
			id="file-upload"
			ref="fileUploadInput"
			multiple
			type="file"
			tabindex="-1"
			aria-hidden="true"
			@change="handleFileInput">
	</div>
</template>

<script>
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Blur from 'vue-material-design-icons/Blur.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import CheckBold from 'vue-material-design-icons/CheckBold.vue'
import Folder from 'vue-material-design-icons/Folder.vue'
import ImagePlus from 'vue-material-design-icons/ImagePlus.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { imagePath, generateUrl } from '@nextcloud/router'

import client from '../../services/DavClient.js'
import { findUniquePath } from '../../utils/fileUpload.js'

let picker

export default {
	name: 'VideoBackgroundEditor',

	components: {
		Cancel,
		Blur,
		ImagePlus,
		CheckBold,
		Upload,
		Folder,
		ArrowLeft,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		virtualBackground: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			selectedBackground: undefined,
			showCustomBackgroundPage: false,
		}
	},

	computed: {
		blurPreviewAvailable() {
			return this.virtualBackground.isAvailable()
		},

		backgrounds() {
			return [
				imagePath('spreed', 'backgrounds/1.jpg'),
				imagePath('spreed', 'backgrounds/2.jpg'),
				imagePath('spreed', 'backgrounds/3.jpg'),
				imagePath('spreed', 'backgrounds/4.jpg'),
				imagePath('spreed', 'backgrounds/5.jpg'),
				imagePath('spreed', 'backgrounds/6.jpg'),
			]
		},
	},

	async mounted() {
		// userRoot path
		const userRoot = '/files/' + this.$store.getters.getUserId()

		// Relative background folder path
		const relativeBackgroundsFolderPath = this.$store.getters.getAttachmentFolder() + '/Backgrounds'

		// Absolute backgrounds folder path
		const absoluteBackgroundsFolderPath = userRoot + relativeBackgroundsFolderPath

		// Create the backgrounds folder if it doesn't exist
		if (await client.exists(absoluteBackgroundsFolderPath) === false) {
			await client.createDirectory(absoluteBackgroundsFolderPath)
		}

		// Create picker
		picker = getFilePickerBuilder(t('spreed', 'File to share'))
			.setMultiSelect(false)
			.setModal(true)
			.startAt(relativeBackgroundsFolderPath)
			.setType(1)
			.allowDirectories(false)
			.build()
	},

	methods: {
		handleSelectBackground(background) {
			this.$emit('update-background', background)
			this.selectedBackground = background
		},

		/**
		 * Clicks the hidden file input and opens the file-picker
		 */
		clickImportInput() {
			this.$refs.fileUploadInput.click()
		},

		async handleFileInput(event) {
			this.showCustomBackgroundPage = false

			// Make file path
			const file = Object.values(event.target.files)[0]

			// Clear input to ensure that the change event will be emitted if
			// the same file is picked again.
			event.target.value = ''

			const fileName = file.name

			// userRoot path
			const userRoot = '/files/' + this.$store.getters.getUserId()

			const filePath = this.$store.getters.getAttachmentFolder() + '/Backgrounds/' + fileName

			// Get a unique relative path based on the previous path variable
			const uniquePath = await findUniquePath(client, userRoot, filePath)

			try {
				// Upload the file
				await client.putFileContents(userRoot + uniquePath, file, {
					contentLength: file.size,
				})

				const previewURL = await generateUrl('/core/preview.png?file={path}&x=-1&y={height}&a=1', {
					path: filePath,
					height: 1080,
				})
				this.$emit('update-background', previewURL)

			} catch (error) {
				console.debug(error)
				showError(t('spreed', 'Error while uploading the file'))
			}

		},

		openPicker() {
			this.showCustomBackgroundPage = false
			picker.pick()
				.then((path) => {
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}

					const previewURL = generateUrl('/core/preview.png?file={path}&x=-1&y={height}&a=1', {
						path,
						height: 1080,
					})

					this.$emit('update-background', previewURL)
				})
		},
	},
}
</script>

<style scoped lang="scss">
.background-editor {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-top: calc(var(--default-grid-baseline) * 4);

	&__element {
		border: none;
		margin: 0;
		border-radius: calc(var(--border-radius-large)* 1.5);
		background: #1cafff2e;
		height: calc(var(--default-grid-baseline) * 16);
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		background-size: cover;

		&--selected {
			box-shadow: inset 0 0 calc(var(--default-grid-baseline) * 4) var(--color-main-background);
		}
	 }
}

</style>
