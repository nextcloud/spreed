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
		<div class="upload-editor">
			<div class="upload-editor__previews">
				<template v-for="file in files">
					<FilePreview
						:key="file.temporaryMessage.id"
						v-bind="file.temporaryMessage.messageParameters.file"
						:is-upload-editor="true" />
				</template>
			</div>
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

export default {
	name: 'UploadEditor',

	components: {
		Modal,
		FilePreview,
	},

	data() {
		return {
			modalDismissed: false,
		}
	},

	computed: {
		currentUploadId() {
			return this.$store.getters.currentUploadId
		},

		files() {
			if (this.currentUploadId) {
				return this.$store.getters.getShareableFiles(this.currentUploadId)
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
	},
}
</script>

<style lang="scss" scoped>

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
}

</style>
