<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
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
	<Modal v-if="showModal"
		@close="handleDismiss">
		<!--native file picker, hidden -->
		<input id="file-upload"
			ref="fileUploadInput"
			multiple
			type="file"
			class="hidden-visually"
			@change="handleFileInput">
		<div class="upload-editor">
			<transition-group
				class="upload-editor__previews"
				name="fade"
				tag="div">
				<template v-for="file in files">
					<FilePreview
						:key="file.temporaryMessage.id"
						v-bind="file.temporaryMessage.messageParameters.file"
						:is-upload-editor="true"
						@remove-file="handleRemoveFileFromSelection" />
				</template>
				<button :key="'addMore'" class="upload-editor__add-more primary" @click="clickImportInput">
					<Plus :size="48" class="upload-editor__plus-icon" />
				</button>
			</transition-group>
			<div class="upload-editor__actions">
				<button @click="handleDismiss">
					Dismiss
				</button>
				<button class="primary" @click="handleUpload">
					Upload
				</button>
			</div>
		</div>
	</Modal>
</template>

<script>

import Modal from '@nextcloud/vue/dist/Components/Modal'
import FilePreview from './MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import Plus from 'vue-material-design-icons/Plus'
import { processFiles } from '../utils/fileUpload'

export default {
	name: 'UploadEditor',

	components: {
		Modal,
		FilePreview,
		Plus,
	},

	data() {
		return {
			modalDismissed: false,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		currentUploadId() {
			return this.$store.getters.currentUploadId
		},

		files() {
			if (this.currentUploadId) {
				return this.$store.getters.getInitialisedUploads(this.currentUploadId)
			}
			return []
		},

		showUploadEditor() {
			return this.$store.getters.showUploadEditor
		},

		showModal() {
			return this.showUploadEditor && !this.modalDismissed
		},
	},

	watch: {
		currentUploadId() {
			this.modalDismissed = false
		},
	},

	methods: {
		handleDismiss() {
			this.modalDismissed = true
		},

		handleUpload() {
			this.$store.dispatch('uploadFiles', this.currentUploadId)
			this.modalDismissed = true
		},
		/**
		 * Clicks the hidden file input when clicking the correspondent ActionButton,
		 * thus opening the file-picker
		 */
		clickImportInput() {
			this.$refs.fileUploadInput.click()
		},

		handleFileInput(event) {
			const files = Object.values(event.target.files)
			processFiles(files, this.token, this.currentUploadId)
		},

		handleRemoveFileFromSelection(id) {
			this.$store.dispatch('removeFileFromSelection', id)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/variables.scss';

.upload-editor {
	height: 100%;
	&__previews {
		display: flex;
		flex-wrap: wrap;
		overflow: auto;
	}
	&__actions {
		display: flex;
		justify-content: space-between;
		margin-bottom: 16px;
		margin-top: 16px;
	}
	&__add-more {
		width: 100px;
		height: 100px;
		border: none;
		border-radius: var(--border-radius-large);
		position: relative;
		z-index: 2;
		box-shadow: 0 0 4px var(--color-box-shadow);
		padding: 0;
		margin: 0;
		&__plus {
			color: var(--color-primary-text);
			z-index: 3;
		}
	}
}

</style>
