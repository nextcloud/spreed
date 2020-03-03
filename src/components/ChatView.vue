<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div
		class="chatView"
		@dragover.prevent="isDraggingOver = true"
		@dragleave.prevent="isDraggingOver = false"
		@drop.prevent="processFiles">
		<transition name="fade" mode="out-in">
			<div
				v-show="isDraggingOver"
				class="dragover">
				<div class="drop-hint">
					<h3
						class="drop-hint__text">
						{{ t('spreed', 'Drop your files here to upload') }}
					</h3>
					<div class="icon-upload drop-hint__icon" />
				</div>
			</div>
		</transition>
		<MessagesList
			:token="token" />
		<NewMessageForm />
	</div>
</template>

<script>
import MessagesList from './MessagesList/MessagesList'
import NewMessageForm from './NewMessageForm/NewMessageForm'
import { shareFile } from '../services/filesSharingServices'

export default {

	name: 'ChatView',

	components: {
		MessagesList,
		NewMessageForm,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	data: function() {
		return {
			isDraggingOver: false,
		}
	},

	methods: {
		/**
		 * Uploads and shares the selected files
		 * @param {object} event the file input event object
		 */
		async processFiles(event) {
			// restore non dragover state
			this.isDraggingOver = false
			// Store the token in a variable to prevent changes when changing conversation
			// when the upload is still running
			const token = this.token
			// Create a unique id for the upload operation
			const uploadId = new Date().getTime()
			// The selected files array coming from the input
			const files = Object.values(event.dataTransfer.files)
			// Process these files in the store
			await this.$store.dispatch('uploadFiles', { uploadId, token, files })
			// Get the files that have successfully been uploaded from the store
			const shareableFiles = this.$store.getters.getShareableFiles(uploadId)
			// Share each of those files in the conversation
			for (const index in shareableFiles) {
				const path = shareableFiles[index].sharePath
				try {
					this.$store.dispatch('markFileAsSharing', { uploadId, index })
					await shareFile(path, token)
					this.$store.dispatch('markFileAsShared', { uploadId, index })
				} catch (exception) {
					console.debug('An error happened when triying to share your file: ', exception)
				}
			}
		},
	},

}
</script>

<style lang="scss" scoped>
.chatView {
	height: 100%;

	display: flex;
	flex-direction: column;
	flex-grow: 1;
}

.dragover {
	width: 100%;
	height: 100%;
	background: var(--color-primary-light);
	z-index: 10;
}

.drop-hint {
	margin: auto;
}

.fade {
	&-enter {
		opacity: 0;
	}
	&-enter-to {
		opacity: 1;
	}
	&-leave {
		opacity: 1;
	}
	&-leave-to {
		opacity: 0;
	}
	&-enter-active,
	&-leave-active {
		transition: all 200ms ease-in-out;
	}
}
</style>
