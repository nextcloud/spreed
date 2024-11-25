<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal v-if="showModal"
		ref="modal"
		:size="isVoiceMessage ? 'small' : 'normal'"
		:close-on-click-outside="false"
		:label-id="dialogHeaderId"
		@close="handleDismiss">
		<div class="upload-editor"
			@dragover.prevent="handleDragOver"
			@dragleave.prevent="handleDragLeave"
			@drop.prevent="handleDropFiles">
			<template v-if="!isVoiceMessage">
				<h2 :id="dialogHeaderId" class="hidden-visually">
					{{ t('spreed', 'Upload from device') }}
				</h2>
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
							is-upload-editor
							:file="file[1].temporaryMessage.messageParameters.file"
							@remove-file="handleRemoveFileFromSelection" />
					</template>
					<NcButton key="add-more"
						:aria-label="addMoreAriaLabel"
						type="tertiary"
						class="add-more-button"
						size="large"
						@click="clickImportInput">
						<template #icon>
							<Plus :size="48" />
						</template>
					</NcButton>
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
				<NcButton ref="submitButton" type="primary" @click="handleLegacyUpload">
					{{ t('spreed', 'Send') }}
				</NcButton>
			</div>
			<NewMessage v-else
				ref="newMessage"
				role="region"
				class="upload-editor__textfield"
				upload
				dialog
				:token="token"
				:container="modalContainerId"
				:aria-label="t('spreed', 'Post message')"
				@submit="handleUpload"
				@dismiss="handleDismiss" />
		</div>
	</NcModal>
</template>

<script>
import { ref } from 'vue'

import Plus from 'vue-material-design-icons/Plus.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import NewMessage from './NewMessage.vue'
import AudioPlayer from '../MessagesList/MessagesGroup/Message/MessagePart/AudioPlayer.vue'
import FilePreview from '../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

import { useId } from '../../composables/useId.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'

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
		const modalContainerId = ref(null)
		const isDraggingOver = ref(false)
		const dialogHeaderId = `new-message-upload-${useId()}`

		return {
			modalContainerId,
			isDraggingOver,
			dialogHeaderId,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		supportMediaCaption() {
			return hasTalkFeature(this.token, 'media-caption')
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
			return this.$store.getters.getLocalUrl(this.firstFile.temporaryMessage.referenceId)
		},
	},

	watch: {
		async showModal(show) {
			if (show) {
				// Wait for modal content to be rendered
				await this.$nextTick()
				this.modalContainerId = `#modal-description-${this.$refs.modal.randId}`
				if (this.supportMediaCaption) {
					this.$refs.newMessage.focusInput()
				} else {
					this.$refs.submitButton.$el.focus()
				}
			}
		},
	},

	methods: {
		t,

		handleDismiss() {
			this.$store.dispatch('discardUpload', this.currentUploadId)
		},

		handleLegacyUpload() {
			this.$store.dispatch('uploadFiles', {
				token: this.token,
				uploadId: this.currentUploadId,
				caption: null,
				options: null,
			})
		},

		async handleUpload({ token, temporaryMessage, options }) {
			if (this.files.length) {
				// Create a share with optional caption
				await this.$store.dispatch('uploadFiles', {
					token,
					uploadId: this.currentUploadId,
					caption: temporaryMessage.message,
					options,
				})
			} else {
				this.$store.dispatch('discardUpload', this.currentUploadId)
				if (temporaryMessage.message.trim()) {
					// Proceed as a normal message
					try {
						await this.$store.dispatch('postNewMessage', { token, temporaryMessage, options })
					} catch (e) {
						console.error(e)
					}
				}
			}
		},
		/**
		 * Clicks the hidden file input when clicking the correspondent NcActionButton,
		 * thus opening the file-picker
		 */
		clickImportInput() {
			this.$refs.fileUploadInput.click()
		},

		handleFileInput(event) {
			const files = Object.values(event.target.files)
			this.$store.dispatch('initialiseUpload', { files, token: this.token, uploadId: this.currentUploadId })
			this.$refs.fileUploadInput.value = null
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
	padding: calc(3 * var(--default-grid-baseline));

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

	&__textfield {
		padding-block: calc(var(--default-grid-baseline) * 2);
	}

	.add-more-button {
		width: 164px !important;
		height: 176px !important;
		margin: 10px;

		:deep(.button-vue__icon) {
			border-radius: var(--border-radius-pill);
			color: var(--color-primary-element-text);
			background-color: var(--color-primary-element);
		}
	}
}

</style>
