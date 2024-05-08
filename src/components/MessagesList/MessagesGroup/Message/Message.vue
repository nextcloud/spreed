<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li :id="`message_${message.id}`"
		ref="message"
		:data-message-id="message.id"
		:data-seen="seen"
		:data-next-message-id="nextMessageId"
		:data-previous-message-id="previousMessageId"
		class="message"
		:class="{'message--highlighted': isHighlighted, 'message--hovered': showMessageButtonsBar}"
		tabindex="0"
		@animationend="isHighlighted = false"
		@mouseover="handleMouseover"
		@mouseleave="handleMouseleave">
		<div :class="{'normal-message-body': !isSystemMessage && !isDeletedMessage,
			'system' : isSystemMessage,
			'combined-system': isCombinedSystemMessage}"
			class="message-body">
			<MessageBody :rich-parameters="richParameters"
				:is-deleting="isDeleting"
				:has-call="conversation.hasCall"
				:message="message"
				:read-info="readInfo" />

			<!-- reactions buttons and popover with details -->
			<Reactions v-if="Object.keys(message.reactions).length"
				:id="message.id"
				:token="message.token"
				:can-react="canReact"
				:show-controls="isHovered || isFollowUpEmojiPickerOpen"
				@emoji-picker-toggled="toggleFollowUpEmojiPicker" />
		</div>

		<!-- Message actions -->
		<div class="message-body__scroll">
			<MessageButtonsBar v-if="showMessageButtonsBar"
				ref="messageButtonsBar"
				class="message-buttons-bar"
				:is-translation-available="isTranslationAvailable"
				:is-action-menu-open.sync="isActionMenuOpen"
				:is-emoji-picker-open.sync="isEmojiPickerOpen"
				:is-reactions-menu-open.sync="isReactionsMenuOpen"
				:is-forwarder-open.sync="isForwarderOpen"
				:can-react="canReact"
				:message="message"
				:previous-message-id="previousMessageId"
				:read-info="readInfo"
				@show-translate-dialog="isTranslateDialogOpen = true"
				@reply="handleReply"
				@edit="handleEdit"
				@delete="handleDelete" />
			<div v-else-if="showCombinedSystemMessageToggle"
				class="message-buttons-bar">
				<NcButton type="tertiary"
					:aria-label="t('spreed', 'Show or collapse system messages')"
					:title="t('spreed', 'Show or collapse system messages')"
					@click="toggleCombinedSystemMessage">
					<template #icon>
						<UnfoldMore v-if="isCombinedSystemMessageCollapsed" />
						<UnfoldLess v-else />
					</template>
				</NcButton>
			</div>
		</div>

		<MessageForwarder v-if="isForwarderOpen"
			:id="message.id"
			:token="message.token"
			@close="isForwarderOpen = false" />

		<MessageTranslateDialog v-if="isTranslationAvailable && isTranslateDialogOpen"
			:message="message.message"
			:rich-parameters="richParameters"
			@close="isTranslateDialogOpen = false" />

		<div v-if="isLastReadMessage"
			v-observe-visibility="lastReadMessageVisibilityChanged"
			class="new-message-marker">
			<span>{{ t('spreed', 'Unread messages') }}</span>
		</div>
	</li>
</template>

<script>
import UnfoldLess from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import UnfoldMore from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'

import { showError, showSuccess, showWarning, TOAST_DEFAULT_TIMEOUT } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import MessageButtonsBar from './MessageButtonsBar/MessageButtonsBar.vue'
import MessageForwarder from './MessageButtonsBar/MessageForwarder.vue'
import MessageTranslateDialog from './MessageButtonsBar/MessageTranslateDialog.vue'
import Contact from './MessagePart/Contact.vue'
import DeckCard from './MessagePart/DeckCard.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import FilePreview from './MessagePart/FilePreview.vue'
import Location from './MessagePart/Location.vue'
import Mention from './MessagePart/Mention.vue'
import MessageBody from './MessagePart/MessageBody.vue'
import Poll from './MessagePart/Poll.vue'
import Reactions from './MessagePart/Reactions.vue'

import { CONVERSATION, PARTICIPANT } from '../../../../constants.js'
import { getTalkConfig } from '../../../../services/CapabilitiesManager.ts'
import { EventBus } from '../../../../services/EventBus.js'
import { useChatExtrasStore } from '../../../../stores/chatExtras.js'
import { getItemTypeFromMessage } from '../../../../utils/getItemTypeFromMessage.ts'

export default {
	name: 'Message',

	components: {
		MessageBody,
		MessageButtonsBar,
		MessageForwarder,
		MessageTranslateDialog,
		NcButton,
		Reactions,
		// Icons
		UnfoldLess,
		UnfoldMore,
	},

	props: {
		message: {
			type: Object,
			required: true,
		},
		/**
		 * Specifies if the message is a combined system message.
		 */
		isCombinedSystemMessage: {
			type: Boolean,
			default: false,
		},
		/**
		 * Specifies whether the combined system message is collapsed.
		 */
		isCombinedSystemMessageCollapsed: {
			type: Boolean,
			default: undefined,
		},
		/**
		 * Specifies if the message is inside a collapsed group.
		 */
		isCollapsedSystemMessage: {
			type: Boolean,
			default: false,
		},
		lastCollapsedMessageId: {
			type: [String, Number],
			default: 0,
		},
		previousMessageId: {
			type: [String, Number],
			default: 0,
		},
		nextMessageId: {
			type: [String, Number],
			default: 0,
		},
	},

	emits: ['toggle-combined-system-message'],

	setup(props) {
		const isTranslationAvailable = getTalkConfig(props.token, 'chat', 'has-translation-providers')
			// Fallback for the desktop client when connecting to Talk 17
			?? getTalkConfig(props.token, 'chat', 'translations')?.length > 0

		return {
			isTranslationAvailable,
			chatExtrasStore: useChatExtrasStore(),
		}
	},

	data() {
		return {
			isHovered: false,
			isDeleting: false,
			isHighlighted: false,
			// whether the message was seen, only used if this was marked as last read message
			seen: false,
			isActionMenuOpen: false,
			// Right side bottom bar
			isEmojiPickerOpen: false,
			// Left side follow-up reaction
			isFollowUpEmojiPickerOpen: false,
			isReactionsMenuOpen: false,
			isForwarderOpen: false,
			isTranslateDialogOpen: false,
		}
	},

	computed: {
		isTemporary() {
			return this.message.timestamp === 0
		},

		isLastMessage() {
			// never displayed for the very last message
			return !this.nextMessageId || this.message.id === this.conversation?.lastMessage?.id
		},

		visualLastLastReadMessageId() {
			return this.$store.getters.getVisualLastReadMessageId(this.message.token)
		},

		isLastReadMessage() {
			if (this.isLastMessage) {
				return false
			}
			return (!this.isCollapsedSystemMessage && this.message.id === this.visualLastLastReadMessageId)
				|| (this.isCollapsedSystemMessage && this.message.id === this.visualLastLastReadMessageId && this.message.id !== this.lastCollapsedMessageId)
				|| (this.isCombinedSystemMessage && this.lastCollapsedMessageId === this.visualLastLastReadMessageId)
		},

		isSystemMessage() {
			return this.message.systemMessage !== ''
		},

		isDeletedMessage() {
			return this.message.messageType === 'comment_deleted'
		},

		conversation() {
			return this.$store.getters.conversation(this.message.token)
		},

		showCommonReadIcon() {
			return this.conversation.lastCommonReadMessage >= this.message.id
				&& this.showSentIcon && !this.isDeletedMessage
		},

		showSentIcon() {
			return !this.isSystemMessage
				&& !this.isTemporary
				&& !this.isDeleting
				&& this.message.actorType === this.$store.getters.getActorType()
				&& this.message.actorId === this.$store.getters.getActorId()
				&& !this.isDeletedMessage
		},

		richParameters() {
			const richParameters = {}
			Object.keys(this.message.messageParameters).forEach(function(p) {
				const type = this.message.messageParameters[p].type
				const mimetype = this.message.messageParameters[p].mimetype
				const itemType = getItemTypeFromMessage({
					messageParameters: this.message.messageParameters,
					messageType: this.message.messageType
				})
				if (type === 'user' || type === 'call' || type === 'guest' || type === 'user-group' || type === 'group') {
					richParameters[p] = {
						component: Mention,
						props: {
							...this.message.messageParameters[p],
							token: this.message.token,
						},
					}
				} else if (type === 'file' && mimetype !== 'text/vcard') {
					richParameters[p] = {
						component: FilePreview,
						props: Object.assign({
							token: this.message.token,
							itemType,
							referenceId: this.message.referenceId,
						}, this.message.messageParameters[p]),
					}
				} else if (type === 'deck-card') {
					richParameters[p] = {
						component: DeckCard,
						props: this.message.messageParameters[p],
					}
				} else if (type === 'geo-location') {
					richParameters[p] = {
						component: Location,
						props: this.message.messageParameters[p],
					}
				} else if (type === 'talk-poll' && this.message.systemMessage !== 'poll_closed') {
					const props = Object.assign({}, this.message.messageParameters[p])
					// Add the token to the component props
					props.token = this.message.token
					richParameters[p] = {
						component: Poll,
						props,
					}
				} else if (mimetype === 'text/vcard') {
					richParameters[p] = {
						component: Contact,
						props: this.message.messageParameters[p],
					}
				} else {
					richParameters[p] = {
						component: DefaultParameter,
						props: this.message.messageParameters[p],
					}
				}
			}.bind(this))
			return richParameters
		},

		showMessageButtonsBar() {
			return !this.isSystemMessage && !this.isDeletedMessage && !this.isTemporary
				&& (this.isHovered || this.isActionMenuOpen || this.isEmojiPickerOpen || this.isFollowUpEmojiPickerOpen
					|| this.isReactionsMenuOpen || this.isForwarderOpen || this.isTranslateDialogOpen)
		},

		showCombinedSystemMessageToggle() {
			return this.isSystemMessage && !this.isDeletedMessage && !this.isTemporary
				&& this.isCombinedSystemMessage && (this.isHovered || !this.isCombinedSystemMessageCollapsed)
		},

		readInfo() {
			return {
				showCommonReadIcon: this.showCommonReadIcon,
				commonReadIconTooltip: t('spreed', 'Message read by everyone who shares their reading status'),
				showSentIcon: this.showSentIcon,
				sentIconTooltip: t('spreed', 'Message sent'),
			}
		},

		canReact() {
			return this.conversation.readOnly !== CONVERSATION.STATE.READ_ONLY
				&& (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT) !== 0
				&& this.message.messageType !== 'command'
				&& this.message.messageType !== 'comment_deleted'
		},
	},

	mounted() {
		EventBus.on('highlight-message', this.highlightMessage)
	},

	beforeDestroy() {
		EventBus.off('highlight-message', this.highlightMessage)
	},

	methods: {
		lastReadMessageVisibilityChanged(isVisible) {
			if (isVisible) {
				this.seen = true
			}
		},

		highlightMessage(messageId) {
			if (this.message.id === messageId) {
				this.isHighlighted = true
			}
		},

		handleMouseover() {
			if (!this.isHovered) {
				this.isHovered = true
			}
		},

		handleMouseleave() {
			if (this.isHovered) {
				this.isHovered = false
			}
		},

		handleReply() {
			this.chatExtrasStore.setParentIdToReply({
				token: this.message.token,
				id: this.message.id,
			})
			EventBus.emit('focus-chat-input')
		},

		handleEdit() {
			this.chatExtrasStore.initiateEditingMessage({
				token: this.message.token,
				id: this.message.id,
				message: this.message.message,
				messageParameters: this.message.messageParameters,
			})
		},

		async handleDelete() {
			this.isDeleting = true
			try {
				const statusCode = await this.$store.dispatch('deleteMessage', {
					token: this.message.token,
					id: this.message.id,
					placeholder: t('spreed', 'Deleting message'),
				})

				if (statusCode === 202) {
					showWarning(t('spreed', 'Message deleted successfully, but Matterbridge is configured and the message might already be distributed to other services'), {
						timeout: TOAST_DEFAULT_TIMEOUT * 2,
					})
				} else if (statusCode === 200) {
					showSuccess(t('spreed', 'Message deleted successfully'))
				}
			} catch (e) {
				if (e?.response?.status === 400) {
					showError(t('spreed', 'Message could not be deleted because it is too old'))
				} else if (e?.response?.status === 405) {
					showError(t('spreed', 'Only normal chat messages can be deleted'))
				} else {
					showError(t('spreed', 'An error occurred while deleting the message'))
					console.error(e)
				}
				this.isDeleting = false
				return
			}

			this.isDeleting = false
		},

		toggleCombinedSystemMessage() {
			this.$emit('toggle-combined-system-message')
		},
		toggleFollowUpEmojiPicker() {
			this.isFollowUpEmojiPickerOpen = !this.isFollowUpEmojiPickerOpen
		},
	},
}
</script>

<style lang="scss" scoped>
.message {
	position: relative;

	&:hover .normal-message-body,
	&:hover .combined-system,
	&--hovered .normal-message-body {
		border-radius: 8px;
		background-color: var(--color-background-hover);
	}
}

.message-body {
	padding: var(--default-grid-baseline);
	padding-left: calc(2 * var(--default-grid-baseline));
	font-size: var(--default-font-size);
	line-height: var(--default-line-height);
	position: relative;

	&__scroll {
		position: absolute;
		top: 0;
		right: 0;
		width: fit-content;
		height: 100%;
		padding: 8px 8px 0 0;
	}
}

.message--highlighted {
	animation: highlight-animation 5s 1;
	border-radius: 8px;
}

@keyframes highlight-animation {
	0% { background-color: var(--color-background-hover); }
	50% { background-color: var(--color-background-hover); }
	100% { background-color: rgba(var(--color-background-hover), 0); }
}

.new-message-marker {
	position: relative;
	margin: 20px 15px;
	border-top: 1px solid var(--color-border);

	span {
		position: absolute;
		top: 0;
		left: 50%;
		transform: translateX(-50%) translateY(-50%);
		padding: 0 7px 0 7px;
		text-align: center;
		white-space: nowrap;
		color: var(--color-text-light);
		border-radius: var(--border-radius);
		background-color: var(--color-main-background);
	}
}

.message-buttons-bar {
	display: flex;
	right: 14px;
	top: 8px;
	position: sticky;
	background-color: var(--color-main-background);
	border-radius: calc(var(--default-clickable-area) / 2);
	box-shadow: 0 0 4px 0 var(--color-box-shadow);
	height: var(--default-clickable-area);
	z-index: 1;

	& h6 {
		margin-left: auto;
	}
}
</style>
