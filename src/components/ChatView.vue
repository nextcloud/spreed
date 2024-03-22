<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="chatView"
		@dragover.prevent="handleDragOver"
		@dragleave.prevent="handleDragLeave"
		@drop.prevent="handleDropFiles">
		<GuestWelcomeWindow v-if="isGuestWithoutDisplayName" :token="token" />
		<TransitionWrapper name="slide-up" mode="out-in">
			<div v-show="isDraggingOver"
				class="dragover">
				<div class="drop-hint">
					<div class="drop-hint__icon"
						:class="{
							'icon-upload' : !isGuest && !isReadOnly,
							'icon-user' : isGuest,
							'icon-error' : isReadOnly}" />
					<h2 class="drop-hint__text">
						{{ dropHintText }}
					</h2>
				</div>
			</div>
		</TransitionWrapper>
		<MessagesList role="region"
			:aria-label="t('spreed', 'Conversation messages')"
			:token="token"
			:is-chat-scrolled-to-bottom.sync="isChatScrolledToBottom"
			:is-visible="isVisible" />

		<div class="scroll-to-bottom">
			<TransitionWrapper name="fade">
				<NcButton v-show="!isChatScrolledToBottom"
					type="secondary"
					:aria-label="t('spreed', 'Scroll to bottom')"
					class="scroll-to-bottom__button"
					@click="smoothScrollToBottom">
					<template #icon>
						<ChevronDoubleDown :size="20" />
					</template>
				</NcButton>
			</TransitionWrapper>
		</div>

		<!-- Input field -->
		<NewMessage v-if="containerId"
			:key="containerId"
			role="region"
			:token="token"
			:container="containerId"
			has-typing-indicator
			:aria-label="t('spreed', 'Post message')" />

		<!-- File upload dialog -->
		<NewMessageUploadEditor />
	</div>
</template>

<script>
import ChevronDoubleDown from 'vue-material-design-icons/ChevronDoubleDown.vue'

import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import GuestWelcomeWindow from './GuestWelcomeWindow.vue'
import MessagesList from './MessagesList/MessagesList.vue'
import NewMessage from './NewMessage/NewMessage.vue'
import NewMessageUploadEditor from './NewMessage/NewMessageUploadEditor.vue'
import TransitionWrapper from './TransitionWrapper.vue'

import { CONVERSATION, PARTICIPANT } from '../constants.js'
import { EventBus } from '../services/EventBus.js'
import { useChatExtrasStore } from '../stores/chatExtras.js'

const attachmentsAllowed = getCapabilities()?.spreed?.config?.attachments?.allowed
const supportFederationV1 = getCapabilities()?.spreed?.features?.includes('federation-v1')

export default {

	name: 'ChatView',

	components: {
		NcButton,
		ChevronDoubleDown,
		MessagesList,
		NewMessage,
		NewMessageUploadEditor,
		TransitionWrapper,
		GuestWelcomeWindow,
	},

	props: {
		isVisible: {
			type: Boolean,
			default: true,
		},
	},

	setup() {
		return {
			chatExtrasStore: useChatExtrasStore(),
		}
	},

	data() {
		return {
			isChatScrolledToBottom: true,
			isDraggingOver: false,
			containerId: undefined,
		}
	},

	computed: {
		isGuest() {
			return this.$store.getters.isActorGuest()
		},

		isGuestWithoutDisplayName() {
			const userName = this.$store.getters.getDisplayName()
			return !userName && this.isGuest
		},

		canUploadFiles() {
			return attachmentsAllowed && this.$store.getters.getUserId()
				&& this.$store.getters.getAttachmentFolderFreeSpace() !== 0
				&& (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT)
				&& (!supportFederationV1 || !this.conversation.remoteServer)
		},

		isDragAndDropBlocked() {
			return this.chatExtrasStore.getMessageIdToEdit(this.token) !== undefined || !this.canUploadFiles
		},

		dropHintText() {
			if (this.isGuest) {
				return t('spreed', 'You need to be logged in to upload files')
			} else if (this.isReadOnly) {
				return t('spreed', 'This conversation is read-only')
			} else {
				return t('spreed', 'Drop your files to upload')
			}
		},
		isReadOnly() {
			if (this.conversation) {
				return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
			} else {
				return undefined
			}
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		}
	},

	watch: {
		container(value) {
			this.containerId = value
		},
	},

	mounted() {
		// Postpone render of NewMessage until application is mounted
		this.containerId = this.container
	},

	methods: {

		handleDragOver(event) {
			if (event.dataTransfer.types.includes('Files') && !this.isDragAndDropBlocked) {
				this.isDraggingOver = true
			}
		},

		handleDragLeave(event) {
			if (!event.currentTarget.contains(event.relatedTarget)) {
				this.isDraggingOver = false
			}
		},

		handleDropFiles(event) {
			if (!this.isDraggingOver || this.isDragAndDropBlocked) {
				return
			}

			// Restore non dragover state
			this.isDraggingOver = false
			// Stop the executin if the user is a guest
			if (this.isGuest || this.isReadOnly) {
				return
			}
			// Get the files from the event
			const files = Object.values(event.dataTransfer.files)
			// Create a unique id for the upload operation
			const uploadId = new Date().getTime()
			// Uploads and shares the files
			this.$store.dispatch('initialiseUpload', { files, token: this.token, uploadId })
		},

		smoothScrollToBottom() {
			EventBus.$emit('scroll-chat-to-bottom', { smooth: true, force: true })
		},
	},

}
</script>

<style lang="scss" scoped>
.chatView {
	width: 100%;
	height: 100%;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	min-height: 0;
}

.dragover {
	position: absolute;
	top: 10%;
	left: 10%;
	width: 80%;
	height: 80%;
	background: var(--color-primary-element-light);
	z-index: 11;
	display: flex;
	box-shadow: 0 0 36px var(--color-box-shadow);
	border-radius: var(--border-radius);
	opacity: 90%;
	pointer-events: none;
}

.drop-hint {
	margin: auto;
	&__icon {
		background-size: 48px;
		height: 48px;
		margin-bottom: 16px;
	}
}

.scroll-to-bottom {
	position: relative;
	height: 0;

	&__button {
		position: absolute !important;
		bottom: 8px;
		right: 24px;
		z-index: 2;
	}
}
</style>
