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
		class="top"
		@close="handleDismiss">
		<div class="conversation-picture-editor"
			@click.stop="">
			<!--native file picker, hidden -->
			<input
				ref="conversationPictureInput"
				type="file"
				accept="image/png, image/jpeg"
				class="hidden-visually"
				@change="handleFileInput">
			<Cropper
				:src="selectedPicture"
				stencil-component="circle-stencil"
				:stencil-props="{
					movable: true,
					scalable: true,
					aspectRatio: 1,
				}"
				:resize-image="{
					adjustStencil: false
				}"
				image-restriction="stencil" />
			<div class="actions" />
			<div class="conversation-picture-editor__actions">
				<button @click="handleDismiss">
					{{ t('spreed', 'Dismiss') }}
				</button>
				<button ref="submitButton" class="primary" @click="handleUpload">
					{{ t('spreed', 'Send') }}
				</button>
			</div>
		</div>
	</Modal>
</template>

<script>

import Modal from '@nextcloud/vue/dist/Components/Modal'
import { Cropper } from 'vue-advanced-cropper'

export default {
	name: 'ConversationPictureEditor',

	components: {
		Modal,
		Cropper,
	},

	data() {
		return {
			selectedPicture: 'https://nextcloud.local/remote.php/dav/files/admin/Peek%202020-11-12%2014-23.gif',
			croppedPicture: null,
		}
	},

	mounted() {
		// Upon mounting the component, click the invisible file input right away

		// this.$nextTick(() => {
		// this.clickInput()
		// })
	},

	methods: {
		focus() {
			this.$nextTick(() => {
				this.$refs.submitButton.focus()
			})
		},

		handleDismiss() {
			this.$emit('close')
		},

		handleUpload() {
			this.$store.dispatch('uploadFiles', this.currentUploadId)
		},
		/**
		 * Clicks the hidden file input when clicking the correspondent ActionButton,
		 * thus opening the file-picker
		 */
		clickInput() {
			console.debug('input', this.$refs.conversationPictureInput)
			this.$refs.conversationPictureInput.click()
		},

		handleFileInput(event) {
			console.debug(event)
			this.selectedPicture = event.target.files[0]
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables.scss';

.conversation-picture-editor {
	max-width: 400px;
	&__actions {
		display: flex;
		justify-content: flex-end;
		margin-top: 16px;
		margin-bottom: 4px;
		button {
			margin: 0 4px 0 4px;
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
