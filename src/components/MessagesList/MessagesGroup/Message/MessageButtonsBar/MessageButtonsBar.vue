<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<!-- Message Actions -->
	<div v-click-outside="handleClickOutside"
		class="message-buttons-bar"
		:class="{ 'message-buttons-bar--last-read' : isLastRead }">
		<template v-if="!isReactionsMenuOpen">
			<NcButton v-if="canReact"
				type="tertiary"
				:aria-label="t('spreed', 'Add a reaction to this message')"
				@click="openReactionsMenu">
				<template #icon>
					<EmoticonOutline :size="20" />
				</template>
			</NcButton>
			<NcActions v-show="isReplyable">
				<NcActionButton @click.stop="handleReply">
					<template #icon>
						<Reply :size="16" />
					</template>
					{{ t('spreed', 'Reply') }}
				</NcActionButton>
			</NcActions>
			<NcActions :force-menu="true"
				:container="`#message_${id}`"
				placement="bottom-end"
				:boundaries-element="containerElement"
				@open="onMenuOpen"
				@close="onMenuClose">
				<NcActionButton v-if="isPrivateReplyable"
					icon="icon-user"
					:close-after-click="true"
					@click.stop="handlePrivateReply">
					{{ t('spreed', 'Reply privately') }}
				</NcActionButton>
				<NcActionButton icon="icon-external"
					:close-after-click="true"
					@click.stop.prevent="handleCopyMessageLink">
					{{ t('spreed', 'Copy message link') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true"
					@click.stop="handleMarkAsUnread">
					<template #icon>
						<EyeOffOutline :size="16" />
					</template>
					{{ t('spreed', 'Mark as unread') }}
				</NcActionButton>
				<NcActionLink v-if="linkToFile"
					:href="linkToFile">
					<File slot="icon"
						:size="20" />
					{{ t('spreed', 'Go to file') }}
				</NcActionLink>
				<NcActionButton v-if="!isCurrentGuest && !isFileShare && !isDeletedMessage"
					:close-after-click="true"
					@click.stop="openForwarder">
					<template #icon>
						<Share :size="16" />
					</template>
					{{ t('spreed', 'Forward message') }}
				</NcActionButton>
				<NcActionSeparator v-if="messageActions.length > 0" />
				<template v-for="action in messageActions">
					<NcActionButton :key="action.label"
						:icon="action.icon"
						:close-after-click="true"
						@click="action.callback(messageApiData)">
						{{ action.label }}
					</NcActionButton>
				</template>
				<template v-if="isDeleteable">
					<NcActionSeparator />
					<NcActionButton icon="icon-delete"
						:close-after-click="true"
						@click.stop="handleDelete">
						{{ t('spreed', 'Delete') }}
					</NcActionButton>
				</template>
			</NcActions>
		</template>

		<template v-if="isReactionsMenuOpen">
			<NcButton type="tertiary"
				:aria-label="t('spreed', 'Close reactions menu')"
				@click="closeReactionsMenu">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
			</NcButton>
			<NcButton type="tertiary"
				:aria-label="t('spreed', 'React with {emoji}', { emoji: '👍' })"
				@click="handleReactionClick('👍')">
				<template #icon>
					<span>👍</span>
				</template>
			</NcButton>
			<NcButton type="tertiary"
				:aria-label="t('spreed', 'React with {emoji}', { emoji: '❤' })"
				@click="handleReactionClick('❤️')">
				<template #icon>
					<span>❤️</span>
				</template>
			</NcButton>
			<NcEmojiPicker :container="`#message_${id} .message-buttons-bar`"
				@select="handleReactionClick"
				@after-show="onEmojiPickerOpen"
				@after-hide="onEmojiPickerClose">
				<NcButton type="tertiary"
					:aria-label="t('spreed', 'React with another emoji')">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcButton>
			</NcEmojiPicker>
		</template>
		<Forwarder v-if="isForwarderOpen"
			:message-object="messageObject"
			@close="closeForwarder" />
	</div>
</template>

<script>
import { PARTICIPANT, CONVERSATION, ATTENDEE } from '../../../../../constants.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import EyeOffOutline from 'vue-material-design-icons/EyeOffOutline.vue'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import File from 'vue-material-design-icons/File.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Reply from 'vue-material-design-icons/Reply.vue'
import Share from 'vue-material-design-icons/Share.vue'
import moment from '@nextcloud/moment'
import { EventBus } from '../../../../../services/EventBus.js'
import { generateUrl } from '@nextcloud/router'
import {
	showError,
	showSuccess,
} from '@nextcloud/dialogs'
import Forwarder from './Forwarder.vue'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'

export default {
	name: 'MessageButtonsBar',

	components: {
		NcActions,
		NcActionButton,
		NcActionLink,
		EyeOffOutline,
		File,
		Share,
		NcActionSeparator,
		Forwarder,
		NcButton,
		EmoticonOutline,
		ArrowLeft,
		Plus,
		Reply,
		NcEmojiPicker,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		previousMessageId: {
			type: [String, Number],
			required: true,
		},

		isReplyable: {
			type: Boolean,
			required: true,
		},

		messageObject: {
			type: Object,
			required: true,
		},

		actorId: {
			type: String,
			required: true,
		},

		actorType: {
			type: String,
			required: true,
		},

		/**
		 * The display name of the sender of the message.
		 */
		actorDisplayName: {
			type: String,
			required: true,
		},

		/**
		 * The parameters of the rich object message
		 */
		messageParameters: {
			type: [Array, Object],
			required: true,
		},

		/**
		 * The message timestamp.
		 */
		timestamp: {
			type: Number,
			default: 0,
		},

		/**
		 * The message id.
		 */
		id: {
			type: [String, Number],
			required: true,
		},

		/**
		 * The parent message's id.
		 */
		parent: {
			type: Number,
			default: 0,
		},

		/**
		 * The message or quote text.
		 */
		message: {
			type: String,
			required: true,
		},

		/**
		 * The type of system message
		 */
		systemMessage: {
			type: String,
			required: true,
		},

		/**
		 * The type of the message.
		 */
		messageType: {
			type: String,
			required: true,
		},

		/**
		 * The participant object.
		 */
		participant: {
			type: Object,
			required: true,
		},

		messageApiData: {
			type: Object,
			required: true,
		},

		isReactionsMenuOpen: {
			type: Boolean,
			required: true,
		},

		isForwarderOpen: {
			type: Boolean,
			required: true,
		},

		canReact: {
			type: Boolean,
			required: true,
		},

		/**
		 * If the MessageButtonsBar belongs to the last read message, we need
		 * to raise it to compensate for the shift in position brought by the
		 * last read marker that's added to the message component.
		 */
		isLastRead: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		containerElement() {
			return document.querySelector('.messages-list__scroller')
		},

		isDeleteable() {
			if (this.isConversationReadOnly) {
				return false
			}

			return (moment(this.timestamp * 1000).add(6, 'h')) > moment()
				&& (this.messageType === 'comment' || this.messageType === 'voice-message')
				&& !this.isDeleting
				&& (this.isMyMsg
					|| (this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE
						&& (this.participant.participantType === PARTICIPANT.TYPE.OWNER
							|| this.participant.participantType === PARTICIPANT.TYPE.MODERATOR)))
		},

		isPrivateReplyable() {
			return this.isReplyable
				&& (this.conversation.type === CONVERSATION.TYPE.PUBLIC
					|| this.conversation.type === CONVERSATION.TYPE.GROUP)
				&& !this.isMyMsg
				&& this.actorType === ATTENDEE.ACTOR_TYPE.USERS
				&& this.$store.getters.getActorType() === ATTENDEE.ACTOR_TYPE.USERS
		},

		messageActions() {
			return this.$store.getters.messageActions
		},

		linkToFile() {
			if (this.isFileShare) {
				return this.messageParameters?.file?.link
			}
			return ''
		},

		isFileShare() {
			return this.message === '{file}' && this.messageParameters?.file
		},

		isCurrentGuest() {
			return this.$store.getters.getActorType() === 'guests'
		},

		isMyMsg() {
			return this.actorId === this.$store.getters.getActorId()
				&& this.actorType === this.$store.getters.getActorType()
		},

		isConversationReadOnly() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
		},

		isDeletedMessage() {
			return this.messageType === 'comment_deleted'
		},
	},

	methods: {
		handleReply() {
			this.$store.dispatch('addMessageToBeReplied', {
				id: this.id,
				actorId: this.actorId,
				actorType: this.actorType,
				actorDisplayName: this.actorDisplayName,
				timestamp: this.timestamp,
				systemMessage: this.systemMessage,
				messageType: this.messageType,
				message: this.message,
				messageParameters: this.messageParameters,
				token: this.token,
				previousMessageId: this.previousMessageId,
			})
			EventBus.$emit('focus-chat-input')
		},

		async handlePrivateReply() {
			// open the 1:1 conversation
			const conversation = await this.$store.dispatch('createOneToOneConversation', this.actorId)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		async handleCopyMessageLink() {
			try {
				const link = window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.token) + '#message_' + this.id
				await this.$copyText(link)
				showSuccess(t('spreed', 'Message link copied to clipboard'))
			} catch (error) {
				console.error('Error copying link: ', error)
				showError(t('spreed', 'The link could not be copied'))
			}
		},

		async handleMarkAsUnread() {
			// update in backend + visually
			await this.$store.dispatch('updateLastReadMessage', {
				token: this.token,
				id: this.previousMessageId,
				updateVisually: true,
			})

			// reload conversation to update additional attributes that have computed values
			await this.$store.dispatch('fetchConversation', { token: this.token })
		},

		handleReactionClick(selectedEmoji) {
			// Add reaction only if user hasn't reacted yet
			if (!this.$store.getters.userHasReacted(this.$store.getters.getActorType(), this.$store.getters.getActorId(), this.token, this.messageObject.id, selectedEmoji)) {
				this.$store.dispatch('addReactionToMessage', {
					token: this.token,
					messageId: this.messageObject.id,
					selectedEmoji,
					actorId: this.actorId,
				})
			} else {
				console.debug('user has already reacted, removing reaction')
				this.$store.dispatch('removeReactionFromMessage', {
					token: this.token,
					messageId: this.id,
					selectedEmoji,
					actorId: this.actorId,
				})
			}
			this.closeReactionsMenu()
		},

		handleDelete() {
			this.$emit('delete')
		},

		onMenuOpen() {
			this.$emit('update:isActionMenuOpen', true)
		},

		onMenuClose() {
			this.$emit('update:isActionMenuOpen', false)
		},

		onEmojiPickerOpen() {
			this.$emit('update:isEmojiPickerOpen', true)
		},

		onEmojiPickerClose() {
			this.$emit('update:isEmojiPickerOpen', false)
		},

		openReactionsMenu() {
			this.$emit('update:isReactionsMenuOpen', true)
		},

		openForwarder() {
			this.$emit('update:isForwarderOpen', true)
		},

		closeForwarder() {
			this.$emit('update:isForwarderOpen', false)
		},

		// Making sure that the click is outside the MessageButtonsBar
		handleClickOutside(event) {
			if (event.composedPath().indexOf(this.$el) !== -1) {
				return
			}
			this.closeReactionsMenu()
		},

		closeReactionsMenu() {
			this.$emit('update:isReactionsMenuOpen', false)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../../../assets/variables';

.message-buttons-bar {
	display: flex;
	right: 14px;
	bottom: -4px;
	position: absolute;
	background-color: var(--color-main-background);
	border-radius: calc($clickable-area / 2);
	box-shadow: 0 0 4px 0 var(--color-box-shadow);
	height: 44px;

	& h6 {
		margin-left: auto;
	}

	&--last-read {
		bottom: 36px;
	}
}

</style>
