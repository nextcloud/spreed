<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="chatView"
		@dragover.prevent="handleDragOver"
		@dragleave.prevent="handleDragLeave"
		@drop.prevent="handleDropFiles">
		<GuestWelcomeWindow v-if="showGuestWelcomeWindow" :token="token" />
		<div class="messages-list-dragover-wrapper">
			<TransitionWrapper name="slide-up" mode="out-in">
				<NcEmptyContent v-show="isDraggingOver"
					:name="dropHintText"
					class="dragover">
					<template #icon>
						<IconTrayArrowUp v-if="!isGuest && !isReadOnly" />
						<IconAccountOutline v-else-if="isGuest" />
						<IconAlertOctagonOutline v-else-if="isReadOnly" />
					</template>
				</NcEmptyContent>
			</TransitionWrapper>
			<MessagesList
				v-model:is-chat-scrolled-to-bottom="isChatScrolledToBottom"
				role="region"
				:aria-label="t('spreed', 'Conversation messages')"
				:token="token"
				:is-visible="isVisible" />
		</div>

		<div class="scroll-to-bottom">
			<TransitionWrapper name="fade">
				<NcButton v-show="!isChatScrolledToBottom && !isLoadingChat"
					variant="secondary"
					:aria-label="t('spreed', 'Scroll to bottom')"
					:title="t('spreed', 'Scroll to bottom')"
					class="scroll-to-bottom__button"
					@click="scrollToBottom">
					<template #icon>
						<IconChevronDoubleDown :size="20" />
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
import { t } from '@nextcloud/l10n'
import { provide } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import IconAccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import IconAlertOctagonOutline from 'vue-material-design-icons/AlertOctagonOutline.vue'
import IconChevronDoubleDown from 'vue-material-design-icons/ChevronDoubleDown.vue'
import IconTrayArrowUp from 'vue-material-design-icons/TrayArrowUp.vue'
import GuestWelcomeWindow from './GuestWelcomeWindow.vue'
import MessagesList from './MessagesList/MessagesList.vue'
import NewMessage from './NewMessage/NewMessage.vue'
import NewMessageUploadEditor from './NewMessage/NewMessageUploadEditor.vue'
import TransitionWrapper from './UIShared/TransitionWrapper.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { CONVERSATION, PARTICIPANT } from '../constants.ts'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { EventBus } from '../services/EventBus.ts'
import { useActorStore } from '../stores/actor.ts'
import { useChatExtrasStore } from '../stores/chatExtras.ts'

export default {

	name: 'ChatView',

	components: {
		NcButton,
		NcEmptyContent,
		MessagesList,
		NewMessage,
		NewMessageUploadEditor,
		TransitionWrapper,
		GuestWelcomeWindow,
		// icons
		IconAccountOutline,
		IconAlertOctagonOutline,
		IconChevronDoubleDown,
		IconTrayArrowUp,
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

	setup(props) {
		provide('chatView:isSidebar', props.isSidebar)
		return {
			token: useGetToken(),
			chatExtrasStore: useChatExtrasStore(),
			actorStore: useActorStore(),
		}
	},

	data() {
		return {
			isChatScrolledToBottom: false,
			isDraggingOver: false,
		}
	},

	computed: {
		isGuest() {
			return this.actorStore.isActorGuest
		},

		isGuestWithoutDisplayName() {
			return this.isGuest && !this.actorStore.displayName
		},

		canUploadFiles() {
			return getTalkConfig(this.token, 'attachments', 'allowed') && this.actorStore.userId
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

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		isLoadingChat() {
			return !this.$store.getters.isMessagesListPopulated(this.token)
		},

		showGuestWelcomeWindow() {
			return this.isGuestWithoutDisplayName
				&& !this.conversation.hasCall
				&& !this.conversation.objectType !== CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION
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
			if (this.$route.hash) {
				// Reset the hash from focused message id (but keep the thread id)
				// Scrolling will be handled by the useGetMessages composable
				this.$router.replace({ query: this.$route.query, hash: '' })
			} else {
				// If the hash is already empty, simply scroll to the bottom
				EventBus.emit('scroll-chat-to-bottom', { smooth: false, force: true })
			}
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

.messages-list-dragover-wrapper {
	position: relative;
	flex: 1 0;
	display: flex;
	min-height: 0;
}

.dragover {
	position: absolute;
	inset: 5%;
	background: var(--color-primary-element-light);
	z-index: 11;
	display: flex;
	box-shadow: 0 0 36px var(--color-box-shadow);
	border-radius: var(--border-radius);
	opacity: 90%;
	pointer-events: none;
}

.scroll-to-bottom {
	position: relative;
	height: 0;

	&__button {
		position: absolute !important;
		bottom: 8px;
		inset-inline-end: 24px;
		z-index: 2;
	}
}
</style>
