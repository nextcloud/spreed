<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<NcModal v-if="showModal"
		ref="modal"
		:size="isVoiceMessage ? 'small' : 'normal'"
		class="upload-editor"
		:container="container"
		@close="handleDismiss">
		<div class="upload-editor"
			@dragover.prevent="handleDragOver"
			@dragleave.prevent="handleDragLeave"
			@drop.prevent="handleDropFiles">
			<template v-if="!isVoiceMessage">
				<!--native file picker, hidden -->
				<input id="file-upload"
					ref="fileUploadInput"
					multiple
					type="file"
					class="hidden-visually"
					@change="handleFileInput">
				<TransitionWrapper class="upload-editor__previews"
					:class="{'dragging-over': isDraggingOver}"
					name="fade"
					tag="div"
					group>
					<template v-for="file in files">
						<FilePreview :key="file[1].temporaryMessage.id"
							:token="token"
							v-bind="file[1].temporaryMessage.messageParameters.file"
							:is-upload-editor="true"
							@remove-file="handleRemoveFileFromSelection" />
					</template>
					<div :key="'addMore'"
						class="add-more">
						<NcButton :aria-label="addMoreAriaLabel"
							type="primary"
							class="add-more__button"
							@click="clickImportInput">
							<template #icon>
								<Plus :size="48" />
							</template>
						</NcButton>
					</div>
				</TransitionWrapper>
			</template>
			<template v-else>
				<AudioPlayer :name="voiceMessageName"
					:local-url="voiceMessageLocalURL" />
			</template>
			<div v-if="!supportMediaCaption" class="upload-editor__actions">
				<NcButton type="tertiary" @click="handleDismiss">
					{{ t('spreed', 'Dismiss') }}
				</NcButton>
				<NcButton type="primary" @click="handleUpload(null)">
					{{ t('spreed', 'Send') }}
				</NcButton>
			</div>
			<NewMessage v-else-if="modalContainerId"
				ref="newMessage"
				role="region"
				upload
				:token="token"
				:container="modalContainerId"
				:aria-label="t('spreed', 'Post message')"
				@sent="handleUpload"
				@failure="handleDismiss" />
		</div>
	</NcModal>
</template>

<script>
import Plus from 'vue-material-design-icons/Plus.vue'

import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import AudioPlayer from '../MessagesList/MessagesGroup/Message/MessagePart/AudioPlayer.vue'
import FilePreview from '../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import NewMessage from './NewMessage.vue'
import TransitionWrapper from '../TransitionWrapper.vue'

const supportMediaCaption = getCapabilities()?.spreed?.features?.includes('media-caption')

export default {
	name: 'NewMessageUploadEditor',

	components: {
		NcModal,
		FilePreview,
		Plus,
		AudioPlayer,
		NcButton,
		NewMessage,
		TransitionWrapper,
	},

	setup() {
		return {
			supportMediaCaption,
		}
	},

	data() {
		return {
			modalContainerId: null,
			isDraggingOver: false,
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
			return this.$store.getters.getInitialisedUploads(this.currentUploadId)
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
			return this.files?.at(0)?.at(1)
		},

		// Hide the plus button in case this editor is used while sending a voice message
		isVoiceMessage() {
			if (!this.firstFile) {
				return false
			}
			return this.firstFile.temporaryMessage.messageType === 'voice-message'
		},

		voiceMessageName() {
			if (!this.firstFile?.file?.name) {
				return ''
			}
			return this.firstFile.file.name
		},

		voiceMessageLocalURL() {
			if (!this.firstFile?.file?.localURL) {
				return ''
			}
			return this.firstFile.file.localURL
		},
	},

	watch: {
		async showModal(show) {
			if (show && this.supportMediaCaption) {
				await this.getContainerId()
				this.$nextTick(() => {
					this.$refs.newMessage?.focusInput()
				})
			}
		},
	},

	methods: {
		async getContainerId() {
			this.$nextTick(() => {
				// Postpone render of NewMessage until modal container is mounted
				this.modalContainerId = `#modal-description-${this.$refs.modal.randId}`
			})
		},

		handleDismiss() {
			this.$store.dispatch('discardUpload', this.currentUploadId)
		},

		handleUpload(caption) {
			this.$store.dispatch('uploadFiles', { uploadId: this.currentUploadId, caption })
		},
		/**
		 * Clicks the hidden file input when clicking the correspondent NcActionButton,
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

		handleDragOver(event) {
			if (event.dataTransfer.types.includes('Files')) {
				this.isDraggingOver = true
			}
		},

		handleDragLeave(event) {
			if (!event.currentTarget.contains(event.relatedTarget)) {
				this.isDraggingOver = false
			}
		},

		handleDropFiles(event) {
			if (!this.isDraggingOver) {
				return
			}

			this.isDraggingOver = false

			const files = Object.values(event.dataTransfer.files)
			this.$store.dispatch('initialiseUpload', { files, token: this.token, uploadId: this.currentUploadId })
		},
	},
}
</script>

<style lang="scss" scoped>
.upload-editor {
	height: 100%;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	padding: 16px;

	&__previews {
		display: flex;
		position: relative;
		overflow: auto;
		flex-wrap: wrap;

		&.dragging-over {
			outline: 3px dashed var(--color-primary-element);
			border-radius: var(--border-radius-large);
		}
	}
	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: 4px;
		padding: 12px 0;
	}
}

.add-more {
	width: 180px;
	height: 180px;
	display: flex;
	margin: 10px;
	&__button {
		margin: auto;
	}
}
</style>
