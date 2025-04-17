<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
				<NcButton v-show="!isChatScrolledToBottom && !isLoadingChat"
					type="secondary"
					:aria-label="t('spreed', 'Scroll to bottom')"
					:title="t('spreed', 'Scroll to bottom')"
					class="scroll-to-bottom__button"
					@click="scrollToBottom">
					<template #icon>
						<ChevronDoubleDown :size="20" />
					</template>
				</NcButton>
			</TransitionWrapper>
		</div>

		<!-- Input field -->
		<NewMessage role="region"
			:token="token"
			has-typing-indicator
			:aria-label="t('spreed', 'Post message')" />

		<!-- File upload dialog -->
		<NewMessageUploadEditor />
	</div>
</template>

<script>
import ChevronDoubleDown from 'vue-material-design-icons/ChevronDoubleDown.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import GuestWelcomeWindow from './GuestWelcomeWindow.vue'
import MessagesList from './MessagesList/MessagesList.vue'
import NewMessage from './NewMessage/NewMessage.vue'
import NewMessageUploadEditor from './NewMessage/NewMessageUploadEditor.vue'
import TransitionWrapper from './UIShared/TransitionWrapper.vue'

import { CONVERSATION, PARTICIPANT } from '../constants.js'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { EventBus } from '../services/EventBus.ts'
import { useChatExtrasStore } from '../stores/chatExtras.js'

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

		isSidebar: {
			type: Boolean,
			default: false,
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
			return getTalkConfig(this.token, 'attachments', 'allowed') && this.$store.getters.getUserId()
				&& this.$store.getters.getAttachmentFolderFreeSpace() !== 0
				&& (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT)
				&& !this.conversation.remoteServer // no attachments support in federated conversations
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

		isLoadingChat() {
			return !this.$store.getters.isMessagesListPopulated(this.token)
		},
	},

	methods: {
		t,

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

		scrollToBottom() {
			EventBus.emit('scroll-chat-to-bottom', { smooth: false, force: true })
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
