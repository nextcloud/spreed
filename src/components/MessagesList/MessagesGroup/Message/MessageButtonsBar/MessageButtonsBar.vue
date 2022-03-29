<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@pm.me>
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
	<!-- Message Actions -->
	<div class="message-buttons-bar">
		<template v-if="page === 0">
			<Button v-if="acceptsReactions"
				type="tertiary"
				@click="page = 1">
				<template #icon>
					<EmoticonOutline :size="20" />
				</template>
			</Button>
			<Actions v-show="isReplyable">
				<ActionButton icon="icon-reply"
					@click.stop="handleReply">
					{{ t('spreed', 'Reply') }}
				</ActionButton>
			</Actions>
			<Actions :force-menu="true"
				:container="`#message_${id}`"
				:boundaries-element="containerElement"
				@open="onMenuOpen"
				@close="onMenuClose">
				<ActionButton v-if="isPrivateReplyable"
					icon="icon-user"
					:close-after-click="true"
					@click.stop="handlePrivateReply">
					{{ t('spreed', 'Reply privately') }}
				</ActionButton>
				<ActionButton icon="icon-external"
					:close-after-click="true"
					@click.stop.prevent="handleCopyMessageLink">
					{{ t('spreed', 'Copy message link') }}
				</ActionButton>
				<ActionButton :close-after-click="true"
					@click.stop="handleMarkAsUnread">
					<template #icon>
						<EyeOffOutline decorative
							title=""
							:size="16" />
					</template>
					{{ t('spreed', 'Mark as unread') }}
				</ActionButton>
				<ActionLink v-if="linkToFile"
					icon="icon-text"
					:href="linkToFile">
					{{ t('spreed', 'Go to file') }}
				</ActionLink>
				<ActionButton v-if="!isCurrentGuest && !isFileShare"
					:close-after-click="true"
					@click.stop="showForwarder = true">
					<Share slot="icon"
						:size="16"
						decorative
						title="" />
					{{ t('spreed', 'Forward message') }}
				</ActionButton>
				<ActionSeparator v-if="messageActions.length > 0" />
				<template v-for="action in messageActions">
					<ActionButton :key="action.label"
						:icon="action.icon"
						:close-after-click="true"
						@click="action.callback(messageApiData)">
						{{ action.label }}
					</ActionButton>
				</template>
				<template v-if="isDeleteable">
					<ActionSeparator />
					<ActionButton icon="icon-delete"
						:close-after-click="true"
						@click.stop="handleDelete">
						{{ t('spreed', 'Delete') }}
					</ActionButton>
				</template>
			</Actions>
		</template>

		<template v-if="page === 1">
			<Button type="tertiary"
				@click="page = 0">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
			</Button>
			<Button type="tertiary"
				@click="handleReactionClick('👍')">
				<template #icon>
					<span>👍</span>
				</template>
			</Button>
			<Button type="tertiary"
				@click="handleReactionClick('❤️')">
				<template #icon>
					<span>❤️</span>
				</template>
			</Button>
			<EmojiPicker :container="`#message_${id}`"
				@select="handleReactionClick"
				@after-show="onEmojiPickerOpen"
				@after-hide="onEmojiPickerClose">
				<Button type="tertiary">
					<template #icon>
						<Plus :size="20" />
					</template>
				</Button>
			</EmojiPicker>
		</template>
		<Forwarder v-if="showForwarder"
			:message-object="messageObject"
			@close="showForwarder = false" />
	</div>
</template>

<script>
import { PARTICIPANT, CONVERSATION, ATTENDEE } from '../../../../../constants'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import EyeOffOutline from 'vue-material-design-icons/EyeOffOutline'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Share from 'vue-material-design-icons/Share'
import moment from '@nextcloud/moment'
import { EventBus } from '../../../../../services/EventBus'
import { generateUrl } from '@nextcloud/router'
import {
	showError,
	showSuccess,
} from '@nextcloud/dialogs'
import Forwarder from '../MessagePart/Forwarder'
import Button from '@nextcloud/vue/dist/Components/Button'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'

export default {
	name: 'MessageButtonsBar',

	components: {
		Actions,
		ActionButton,
		ActionLink,
		EyeOffOutline,
		Share,
		ActionSeparator,
		Forwarder,
		Button,
		EmoticonOutline,
		ArrowLeft,
		Plus,
		EmojiPicker,
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
	},

	data() {
		return {
			// Shows/hides the message forwarder component
			showForwarder: false,

			// The pagination of the buttons menu
			page: 0,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		containerElement() {
			return document.querySelector(this.container)
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

		acceptsReactions() {
			return !this.isConversationReadOnly
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
				showSuccess(t('spreed', 'Message link copied to clipboard.'))
			} catch (error) {
				console.error('Error copying link: ', error)
				showError(t('spreed', 'The link could not be copied.'))
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
	z-index: 100000;
	background-color: var(--color-main-background);
	border-radius: calc($clickable-area / 2);
	box-shadow: 0 0 4px 0px var(--color-box-shadow);

	& h6 {
		margin-left: auto;
	}
}

</style>
