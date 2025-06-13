<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="background-editor">
		<button key="clear"
			class="background-editor__element"
			:class="{ 'background-editor__element--selected': selectedBackground === 'none' }"
			@click="handleSelectBackground('none')">
			<IconCancel :size="20" />
			{{
				// TRANSLATORS: "None" refers to "No background effect applied" in videos, for context, other options are "blur" or "image"
				t('spreed', 'None')
			}}
		</button>
		<button key="blur"
			class="background-editor__element"
			:class="{ 'background-editor__element--selected': selectedBackground === 'blur' }"
			@click="handleSelectBackground('blur')">
			<IconBlur :size="20" />
			{{ t('spreed', 'Blur') }}
		</button>
		<template v-if="predefinedBackgrounds?.length">
			<template v-if="canUploadBackgrounds">
				<button class="background-editor__element"
					@click="clickImportInput">
					<IconUpload :size="20" />
					{{ t('spreed', 'Upload') }}
				</button>
				<button class="background-editor__element"
					:class="{ 'background-editor__element--selected': isCustomBackground }"
					@click="showFilePicker = true">
					<IconFolder :size="20" />
					{{ t('spreed', 'Files') }}
				</button>
			</template>
			<button v-for="path in predefinedBackgroundsURLs"
				:key="path"
				:aria-label="ariaLabelForPredefinedBackground(path)"
				:title="ariaLabelForPredefinedBackground(path)"
				class="background-editor__element"
				:class="{ 'background-editor__element--selected': selectedBackground === path }"
				:style="{
					'background-image': 'url(' + path + ')',
				}"
				@click="handleSelectBackground(path)">
				<IconCheckBold v-if="selectedBackground === path"
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

		<FilePickerVue v-if="showFilePicker"
			:name="t('spreed', 'Select a file')"
			:path="relativeBackgroundsFolderPath"
			container=".media-settings"
			:buttons="filePickerButtons"
			:multiselect="false"
			@close="showFilePicker = false" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { FilePickerVue } from '@nextcloud/dialogs/filepicker.js'
import { t } from '@nextcloud/l10n'
import { generateUrl, imagePath } from '@nextcloud/router'
import IconBlur from 'vue-material-design-icons/Blur.vue'
import IconCancel from 'vue-material-design-icons/Cancel.vue'
import IconCheckBold from 'vue-material-design-icons/CheckBold.vue'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconUpload from 'vue-material-design-icons/Upload.vue'
import { VIRTUAL_BACKGROUND } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { getDavClient } from '../../services/DavClient.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useSettingsStore } from '../../stores/settings.js'
import { findUniquePath } from '../../utils/fileUpload.js'

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

export default {
	name: 'VideoBackgroundEditor',

	components: {
		FilePickerVue,
		IconBlur,
		IconCancel,
		IconCheckBold,
		IconFolder,
		IconUpload,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		skipBlurVirtualBackground: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update-background'],

	setup() {
		return {
			canUploadBackgrounds: getTalkConfig('local', 'call', 'can-upload-background'),
			predefinedBackgrounds: getTalkConfig('local', 'call', 'predefined-backgrounds'),
			predefinedBackgroundsV2: getTalkConfig('local', 'call', 'predefined-backgrounds-v2'),
			settingsStore: useSettingsStore(),
			actorStore: useActorStore(),
		}
	},

	data() {
		return {
			selectedBackground: undefined,
			showFilePicker: false,
		}
	},

	computed: {
		isCustomBackground() {
			return this.selectedBackground !== 'none'
				&& this.selectedBackground !== 'blur'
				&& !this.predefinedBackgroundsURLs.includes(this.selectedBackground)
		},

		predefinedBackgroundsURLs() {
			if (this.predefinedBackgroundsV2) {
				return this.predefinedBackgroundsV2
			}

			return this.predefinedBackgrounds.map((fileName) => {
				return imagePath('spreed', 'backgrounds/' + fileName)
			})
		},

		relativeBackgroundsFolderPath() {
			return this.$store.getters.getAttachmentFolder() + '/Backgrounds'
		},

		filePickerButtons() {
			return [{
				label: t('spreed', 'Confirm'),
				callback: (nodes) => this.handleFileChoose(nodes),
				variant: 'primary',
			}]
		},
	},

	async mounted() {
		this.loadBackground()

		if (this.actorStore.userId === null) {
			console.debug('Skip Talk backgrounds folder check and setup for participants that are not logged in')
			return
		}

		const userRoot = '/files/' + this.actorStore.userId
		const absoluteBackgroundsFolderPath = userRoot + this.relativeBackgroundsFolderPath

		try {
			// Create the backgrounds folder if it doesn't exist
			const client = getDavClient()
			if (await client.exists(absoluteBackgroundsFolderPath) === false) {
				await client.createDirectory(absoluteBackgroundsFolderPath)
			}
		} catch (error) {
			console.debug(error)
		}
	},

	methods: {
		t,
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
			const userRoot = '/files/' + this.actorStore.userId

			const filePath = this.$store.getters.getAttachmentFolder() + '/Backgrounds/' + file.name

			const client = getDavClient()
			// Get a unique relative path based on the previous path variable
			const { uniquePath } = await findUniquePath(client, userRoot, filePath)

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

		handleFileChoose(nodes) {
			const path = nodes[0]?.path
			if (!path) {
				return
			}

			if (!path.startsWith('/')) {
				throw new Error(t('files', 'Invalid path selected'))
			}

			const previewURL = generateUrl('/core/preview.png?file={path}&x=-1&y={height}&a=1', {
				path,
				height: 1080,
			})

			this.handleSelectBackground(previewURL)
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
			} else if (this.settingsStore.blurVirtualBackgroundEnabled && !this.skipBlurVirtualBackground) {
				this.selectedBackground = 'blur'
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
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: calc(var(--default-grid-baseline) * 2);
	margin-top: calc(var(--default-grid-baseline) * 2);

	&__element {
		border: none;
		margin: 0 !important;
		border-radius: var(--border-radius-element, calc(var(--border-radius-large) * 1.5));
		height: calc(var(--default-grid-baseline) * 16);
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		background-color: #1cafff2e;
		background-size: cover;
		background-position: center;
		flex: 1 0 108px;

		&--selected {
			box-shadow: inset 0 0 0 var(--default-grid-baseline) var(--color-primary-element);
		}

		&:focus-visible {
			// Do not overflow container
			outline-offset: -2px; // inline with server's global focus outline
		}
	}
}

</style>
