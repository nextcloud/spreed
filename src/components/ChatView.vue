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
	<div class="chatView"
		@dragover.prevent="handleDragOver"
		@dragleave.prevent="handleDragLeave"
		@drop.prevent="handleDropFiles">
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

		<NewMessage v-if="containerId"
			:key="containerId"
			role="region"
			:token="token"
			:container="containerId"
			has-typing-indicator
			:aria-label="t('spreed', 'Post message')" />

	</div>
</template>

<script>
import ChevronDoubleDown from 'vue-material-design-icons/ChevronDoubleDown.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import MessagesList from './MessagesList/MessagesList.vue'
import NewMessage from './NewMessage/NewMessage.vue'
import TransitionWrapper from './TransitionWrapper.vue'

import { CONVERSATION } from '../constants.js'
import { EventBus } from '../services/EventBus.js'

export default {

	name: 'ChatView',

	components: {
		NcButton,
		ChevronDoubleDown,
		MessagesList,
		NewMessage,
		TransitionWrapper,
	},

	props: {
		isVisible: {
			type: Boolean,
			default: true,
		},
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
			return this.$store.getters.getActorType() === 'guests'
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
			if (this.$store.getters.conversation(this.token)) {
				return this.$store.getters.conversation(this.token).readOnly === CONVERSATION.STATE.READ_ONLY
			} else {
				return undefined
			}
		},

		token() {
			return this.$store.getters.getToken()
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		}
	},

	watch: {
		container(value) {
			this.containerId = value
		}
	},

	mounted() {
		// Postpone render of NewMessage until application is mounted
		this.containerId = this.container
	},

	methods: {

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
			EventBus.$emit('smooth-scroll-chat-to-bottom')
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
