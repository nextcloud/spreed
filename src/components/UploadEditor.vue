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
	<Modal
		v-if="showModal"
		class="upload-editor"
		:container="container"
		@close="handleDismiss">
		<template v-if="!isVoiceMessage">
			<!--native file picker, hidden -->
			<input
				id="file-upload"
				ref="fileUploadInput"
				multiple
				type="file"
				class="hidden-visually"
				@change="handleFileInput">
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
				<div
					:key="'addMore'"
					class="add-more">
					<button
						:aria-label="addMoreAriaLabel"
						class="add-more__button primary"
						@click="clickImportInput">
						<Plus
							decorative
							title=""
							:size="48"
							class="upload-editor__plus-icon" />
					</button>
				</div>
			</transition-group>
		</template>
		<template v-else>
			<AudioPlayer
				:name="voiceMessageName"
				:local-url="voiceMessageLocalURL" />
		</template>
		<div class="upload-editor__actions">
			<button @click="handleDismiss">
				{{ t('spreed', 'Dismiss') }}
			</button>
			<button ref="submitButton" class="primary" @click="handleUpload">
				{{ t('spreed', 'Send') }}
			</button>
		</div>
	</Modal>
</template>

<script>

import Modal from '@nextcloud/vue/dist/Components/Modal'
import FilePreview from './MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import Plus from 'vue-material-design-icons/Plus'
import AudioPlayer from './MessagesList/MessagesGroup/Message/MessagePart/AudioPlayer.vue'

export default {
	name: 'UploadEditor',

	components: {
		Modal,
		FilePreview,
		Plus,
		AudioPlayer,
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

		showModal() {
			return !!this.currentUploadId
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		addMoreAriaLabel() {
			return t('spreed', 'Add more files')
		},

		firstFile() {
			return this.files[Object.keys(this.files)[0]]
		},

		// Hide the plus button in case this editor is used while sending a voice
		// message
		isVoiceMessage() {
			if (!this.firstFile) {
				return false
			}
			return this.firstFile.temporaryMessage.messageType === 'voice-message'
		},

		voiceMessageName() {
			if (!this.firstFile.file.name) {
				return ''
			}
			return this.firstFile.file.name
		},

		voiceMessageLocalURL() {
			if (!this.firstFile.file.localURL) {
				return ''
			}
			return this.firstFile.file.localURL
		},
	},

	watch: {
		showModal(show) {
			if (show) {
				this.focus()
			}
		},
	},

	methods: {
		focus() {
			this.$nextTick(() => {
				this.$refs.submitButton.focus()
			})
		},

		handleDismiss() {
			this.$store.dispatch('discardUpload', this.currentUploadId)
		},

		handleUpload() {
			this.$store.dispatch('uploadFiles', this.currentUploadId)
		},
		/**
		 * Clicks the hidden file input when clicking the correspondent ActionButton,
		 * thus opening the file-picker
		 */
		clickImportInput() {
			this.$refs.fileUploadInput.click()
		},

		async handleFileInput(event) {
			const files = Object.values(event.target.files)
			await this.$store.dispatch('initialiseUpload', { files, token: this.token, uploadId: this.currentUploadId })
		},

		handleRemoveFileFromSelection(id) {
			this.$store.dispatch('removeFileFromSelection', id)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/variables';

.upload-editor {
	&__previews {
		overflow-x: hidden !important;
		display: grid;
		position: relative;
		overflow: auto;
		grid-template-columns: repeat(4, auto);
	}
	&__actions {
		display: flex;
		justify-content: space-between;
		margin-top: 16px;
		margin-bottom: 4px;
		button {
			margin: 0 4px 0 4px;
		}
	}
}

.add-more {
	width: 180px;
	height: 180px;
	display: flex;
	margin: 10px;
	&__button {
		width: 80px;
		height: 80px;
		border: none;
		border-radius: var(--border-radius-pill);
		position: relative;
		z-index: 2;
		box-shadow: 0 0 4px var(--color-box-shadow);
		padding: 0;
		margin: auto;
		&__plus {
			color: var(--color-primary-text);
			z-index: 3;
		}
	}
}

::v-deep .modal-container {
	display: flex !important;
	flex-direction: column;
	padding: 12px !important;
	min-width: 400px;
}
</style>
