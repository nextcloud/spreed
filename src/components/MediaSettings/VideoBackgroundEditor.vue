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
		<button key="clear"
			class="background-editor__element"
			:class="{'background-editor__element--selected': selectedBackground === 'none'}"
			@click="handleSelectBackground('none')">
			<Cancel :size="20" />
			<!-- TRANSLATORS: "None" refers to "No background effect applied" in videos, for context, other options are "blur" or "image"*/ -->
			{{ t('spreed', 'None') }}
		</button>
		<button key="blur"
			class="background-editor__element"
			:class="{'background-editor__element--selected': selectedBackground === 'blur'}"
			@click="handleSelectBackground('blur')">
			<Blur :size="20" />
			{{ t('spreed', 'Blur') }}
		</button>
		<template v-if="predefinedBackgrounds && predefinedBackgrounds.length">
			<template v-if="canUploadBackgrounds">
				<button class="background-editor__element"
					@click="clickImportInput">
					<Upload :size="20" />
					{{ t('spreed', 'Upload') }}
				</button>
				<button class="background-editor__element"
					:class="{'background-editor__element--selected': isCustomBackground }"
					@click="openPicker">
					<Folder :size="20" />
					{{ t('spreed', 'Files') }}
				</button>
			</template>
			<button v-for="path in predefinedBackgroundsURLs"
				:key="path"
				:aria-label="ariaLabelForPredefinedBackground(path)"
				:title="ariaLabelForPredefinedBackground(path)"
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
		<!--native file picker, hidden -->
		<input id="custom-background-file"
			ref="fileUploadInput"
			class="hidden-visually"
			multiple
			type="file"
			tabindex="-1"
			aria-hidden="true"
			@change="handleFileInput">
	</div>
</template>

<script>
import Blur from 'vue-material-design-icons/Blur.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import CheckBold from 'vue-material-design-icons/CheckBold.vue'
import Folder from 'vue-material-design-icons/Folder.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { imagePath, generateUrl } from '@nextcloud/router'

import { VIRTUAL_BACKGROUND } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import client from '../../services/DavClient.js'
import { findUniquePath } from '../../utils/fileUpload.js'

const canUploadBackgrounds = getCapabilities()?.spreed?.config?.call?.['can-upload-background']
const predefinedBackgrounds = getCapabilities()?.spreed?.config?.call?.['predefined-backgrounds']
const predefinedBackgroundLabels = {
	'1_office': t('spreed', 'Select virtual office background'),
	'2_home': t('spreed', 'Select virtual home background'),
	'3_abstract': t('spreed', 'Select virtual abstract background'),
	'4_beach': t('spreed', 'Select virtual beach background'),
	'5_park': t('spreed', 'Select virtual park background'),
	'6_theater': t('spreed', 'Select virtual theater background'),
	'7_library': t('spreed', 'Select virtual library background'),
	'8_space_station': t('spreed', 'Select virtual space station background'),
}

let picker

export default {
	name: 'VideoBackgroundEditor',

	components: {
		Cancel,
		Blur,
		CheckBold,
		Upload,
		Folder,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	emits: ['update-background'],

	setup() {
		return {
			canUploadBackgrounds,
			predefinedBackgrounds,
		}
	},

	data() {
		return {
			selectedBackground: undefined,
			getCapabilities,
		}
	},

	computed: {
		isCustomBackground() {
			return this.selectedBackground !== 'none'
			    && this.selectedBackground !== 'blur'
			    && !this.predefinedBackgroundsURLs.includes(this.selectedBackground)
		},

		predefinedBackgroundsURLs() {
			return predefinedBackgrounds.map(fileName => {
				return imagePath('spreed', 'backgrounds/' + fileName)
			})
		},

		hasBackgroundsCapability() {
			return !!predefinedBackgrounds
		},
	},

	async mounted() {
		this.loadBackground()

		if (this.$store.getters.getUserId() === null) {
			console.debug('Skip Talk backgrounds folder check and setup for participants that are not logged in')
			return
		}

		const userRoot = '/files/' + this.$store.getters.getUserId()
		const relativeBackgroundsFolderPath = this.$store.getters.getAttachmentFolder() + '/Backgrounds'
		const absoluteBackgroundsFolderPath = userRoot + relativeBackgroundsFolderPath

		try {
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
		} catch (error) {
			console.debug(error)
		}
	},

	methods: {
		handleSelectBackground(path) {
			this.$emit('update-background', path)
			this.selectedBackground = path
		},

		/**
		 * Clicks the hidden file input and opens the file-picker
		 */
		clickImportInput() {
			this.$refs.fileUploadInput.click()
		},

		async handleFileInput(event) {

			// Make file path
			const file = event.target.files[0]

			// Clear input to ensure that the change event will be emitted if
			// the same file is picked again.
			event.target.value = ''

			// userRoot path
			const userRoot = '/files/' + this.$store.getters.getUserId()

			const filePath = this.$store.getters.getAttachmentFolder() + '/Backgrounds/' + file.name

			// Get a unique relative path based on the previous path variable
			const uniquePath = await findUniquePath(client, userRoot, filePath)

			try {
				// Upload the file
				const fileBuffer = await new Blob([file]).arrayBuffer()
				await client.putFileContents(userRoot + uniquePath, fileBuffer, {
					contentLength: file.size,
				})

				const previewURL = await generateUrl('/core/preview.png?file={path}&x=-1&y={height}&a=1', {
					path: filePath,
					height: 1080,
				})

				this.handleSelectBackground(previewURL)

			} catch (error) {
				console.debug(error)
				showError(t('spreed', 'Error while uploading the file'))
			}
		},

		openPicker() {
			picker.pick()
				.then((path) => {
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}

					const previewURL = generateUrl('/core/preview.png?file={path}&x=-1&y={height}&a=1', {
						path,
						height: 1080,
					})

					this.handleSelectBackground(previewURL)
				})
		},

		loadBackground() {
			// Set virtual background depending on browser storage's settings
			if (BrowserStorage.getItem('virtualBackgroundEnabled_' + this.token) === 'true') {
				if (BrowserStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR) {
					this.selectedBackground = 'blur'
				} else if (BrowserStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
					this.selectedBackground = BrowserStorage.getItem('virtualBackgroundUrl_' + this.token)
				} else {
					this.selectedBackground = 'none'
				}
			} else {
				this.selectedBackground = 'none'
			}
		},

		ariaLabelForPredefinedBackground(path) {
			const fileName = path.split('/').pop().split('.').shift()

			return predefinedBackgroundLabels[fileName]
				?? t('spreed', 'Select virtual background from file {fileName}', { fileName })
		},
	},
}
</script>

<style scoped lang="scss">
.background-editor {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-top: calc(var(--default-grid-baseline) * 2);

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
		background-position: center;
		flex: 1 0 108px;

		&--selected {
			box-shadow: inset 0 0 0 var(--default-grid-baseline) var(--color-primary-element);
		}
	 }
}

</style>
