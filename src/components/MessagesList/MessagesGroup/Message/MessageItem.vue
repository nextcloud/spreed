<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		:id="`message_${message.id}`"
		:data-message-id="message.id"
		:data-next-message-id="nextMessageId"
		:data-previous-message-id="previousMessageId"
		class="message"
		:class="{
			'message--hovered': showMessageButtonsBar,
			'message--pinned': !isSplitViewEnabled && isPinned,
			'message--sided': isSplitViewEnabled,
			'message--small-view': (isSmallMobile || isSidebar) && isSplitViewEnabled,
			'message--deleted': isDeletedMessage,
		}"
		tabindex="0"
		@mouseover="handleMouseover"
		@mouseleave="handleMouseleave">
		<div class="message-body">
			<MessageBody
				:rich-parameters="richParameters"
				:is-deleting="isDeleting"
				:has-call="conversation.hasCall"
				:message="message"
				:read-info="readInfo"
				:is-short-simple-message
				:is-self-actor>
				<!-- reactions buttons and popover with details -->
				<ReactionsWrapper
					v-if="Object.keys(message.reactions).length"
					:id="message.id"
					:token="message.token"
					:can-react="canReact"
					:show-controls="isHovered || isFollowUpEmojiPickerOpen"
					:is-self-actor
					@emoji-picker-toggled="toggleFollowUpEmojiPicker" />
			</MessageBody>
		</div>

		<!-- Message actions -->
		<div
			class="message-body__scroll"
			:class="{
				'bottom-side': isSplitViewEnabled && !isShortSimpleMessage && (isSmallMobile || isSidebar) && (!isPinned || showMessageButtonsBar),
				overlay: isSplitViewEnabled && !isShortSimpleMessage && isReactionsMenuOpen && !(isSmallMobile || isSidebar),
			}">
			<template v-if="showMessageButtonsBar">
				<ScheduledMessageActions
					v-if="showScheduledMessages"
					v-model:is-action-menu-open="isActionMenuOpen"
					:message="message"
					class="message-buttons-bar"
					:class="{ outlined: buttonsBarOutlined }"
					@edit="handleEdit" />
				<MessageButtonsBar
					v-else
					v-model:is-action-menu-open="isActionMenuOpen"
					v-model:is-emoji-picker-open="isEmojiPickerOpen"
					v-model:is-reactions-menu-open="isReactionsMenuOpen"
					v-model:is-forwarder-open="isForwarderOpen"
					class="message-buttons-bar"
					:class="{ outlined: buttonsBarOutlined }"
					:is-translation-available="isTranslationAvailable"
					:can-react="canReact"
					:message="message"
					:previous-message-id="previousMessageId"
					:read-info="readInfo"
					@show-translate-dialog="isTranslateDialogOpen = true"
					@reply="handleReply"
					@edit="handleEdit"
					@delete="handleDelete" />
			</template>
			<div
				v-else-if="isSplitViewEnabled && isPinned"
				class="icon-pin-highlighted"
				:title="t('spreed', 'Pinned')"
				:aria-label="t('spreed', 'Pinned')">
				<IconPin :size="16" />
			</div>
		</div>

		<MessageForwarder
			v-if="isForwarderOpen"
			:id="message.id"
			:token="message.token"
			@close="isForwarderOpen = false" />

		<MessageTranslateDialog
			v-if="isTranslationAvailable && isTranslateDialogOpen"
			:message="message.message"
			:rich-parameters="richParameters"
			@close="isTranslateDialogOpen = false" />
	</li>
</template>

<script>
import { showError, showSuccess, showWarning, TOAST_DEFAULT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { useIsSmallMobile } from '@nextcloud/vue/composables/useIsMobile'
import { inject } from 'vue'
import IconPin from 'vue-material-design-icons/PinOutline.vue'
import MessageButtonsBar from './MessageButtonsBar/MessageButtonsBar.vue'
import MessageForwarder from './MessageButtonsBar/MessageForwarder.vue'
import MessageTranslateDialog from './MessageButtonsBar/MessageTranslateDialog.vue'
import ScheduledMessageActions from './MessageButtonsBar/ScheduledMessageActions.vue'
import ContactCard from './MessagePart/ContactCard.vue'
import DeckCard from './MessagePart/DeckCard.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import FilePreview from './MessagePart/FilePreview.vue'
import LocationCard from './MessagePart/LocationCard.vue'
import MentionChip from './MessagePart/MentionChip.vue'
import MessageBody from './MessagePart/MessageBody.vue'
import PollCard from './MessagePart/PollCard.vue'
import ReactionsWrapper from './MessagePart/ReactionsWrapper.vue'
import { useGetThreadId } from '../../../../composables/useGetThreadId.ts'
import { CONVERSATION, MENTION, MESSAGE, PARTICIPANT } from '../../../../constants.ts'
import { getTalkConfig } from '../../../../services/CapabilitiesManager.ts'
import { EventBus } from '../../../../services/EventBus.ts'
import { useActorStore } from '../../../../stores/actor.ts'
import { useChatExtrasStore } from '../../../../stores/chatExtras.ts'
import { getItemTypeFromMessage } from '../../../../utils/getItemTypeFromMessage.ts'

export default {
	name: 'MessageItem',

	components: {
		MessageBody,
		MessageButtonsBar,
		MessageForwarder,
		MessageTranslateDialog,
		ReactionsWrapper,
		ScheduledMessageActions,
		IconPin,
	},

	props: {
		message: {
			type: Object,
			required: true,
		},

		previousMessageId: {
			type: [String, Number],
			default: 0,
		},

		nextMessageId: {
			type: [String, Number],
			default: 0,
		},

		isSelfActor: {
			type: Boolean,
			default: false,
		},
	},

	setup(props) {
		const isTranslationAvailable = getTalkConfig(props.token, 'chat', 'has-translation-providers')
			// Fallback for the desktop client when connecting to Talk 17
			?? getTalkConfig(props.token, 'chat', 'translations')?.length > 0
		const isSidebar = inject('chatView:isSidebar', false)
		const threadId = useGetThreadId()
		const isSplitViewEnabled = inject('messagesList:isSplitViewEnabled', true)

		return {
			isTranslationAvailable,
			chatExtrasStore: useChatExtrasStore(),
			actorStore: useActorStore(),
			isSmallMobile: useIsSmallMobile(),
			isSidebar,
			isSplitViewEnabled,
			threadId,
		}
	},

	data() {
		return {
			isHovered: false,
			isDeleting: false,
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
		isScheduledMessage() {
			return this.message.referenceId?.startsWith('scheduled-')
		},

		isTemporary() {
			return !this.isScheduledMessage && this.message.timestamp === 0
		},

		isDeletedMessage() {
			return this.message.messageType === MESSAGE.TYPE.COMMENT_DELETED
		},

		showScheduledMessages() {
			return this.chatExtrasStore.showScheduledMessages
		},

		conversation() {
			if (this.message.token === '') {
				return this.$store.getters.dummyConversation
			}
			return this.$store.getters.conversation(this.message.token)
		},

		showCommonReadIcon() {
			return this.conversation.lastCommonReadMessage >= this.message.id
				&& this.showSentIcon && !this.isDeletedMessage
		},

		showSentIcon() {
			return !this.isTemporary
				&& !this.isDeleting
				&& this.isSelfActor
				&& !this.isDeletedMessage
		},

		richParameters() {
			const richParameters = {}
			Object.keys(this.message.messageParameters).forEach(function(p) {
				const type = this.message.messageParameters[p].type
				const mimetype = this.message.messageParameters[p].mimetype
				const itemType = getItemTypeFromMessage({
					messageParameters: this.message.messageParameters,
					messageType: this.message.messageType,
				})
				if (Object.values(MENTION.TYPE).includes(type)) {
					richParameters[p] = {
						component: MentionChip,
						props: {
							...this.message.messageParameters[p],
							token: this.message.token,
						},
					}
				} else if (type === 'file' && mimetype !== 'text/vcard') {
					richParameters[p] = {
						component: FilePreview,
						props: {
							token: this.message.token,
							messageId: this.message.id,
							nextMessageId: this.nextMessageId,
							itemType,
							referenceId: this.message.referenceId,
							file: this.message.messageParameters[p],
						},
					}
				} else if (type === 'deck-card') {
					richParameters[p] = {
						component: DeckCard,
						props: this.message.messageParameters[p],
					}
				} else if (type === 'geo-location') {
					richParameters[p] = {
						component: LocationCard,
						props: this.message.messageParameters[p],
					}
				} else if (type === 'talk-poll' && this.message.systemMessage !== 'poll_closed') {
					const props = { ...this.message.messageParameters[p] }
					// Add the token to the component props
					props.token = this.message.token
					richParameters[p] = {
						component: PollCard,
						props,
					}
				} else if (mimetype === 'text/vcard') {
					richParameters[p] = {
						component: ContactCard,
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
			return !this.isDeletedMessage && !this.isTemporary
				&& (this.isHovered || this.isActionMenuOpen || this.isEmojiPickerOpen || this.isFollowUpEmojiPickerOpen
					|| this.isReactionsMenuOpen || this.isForwarderOpen || this.isTranslateDialogOpen)
		},

		readInfo() {
			if (this.showScheduledMessages) {
				return {
					showSilentIcon: this.message.silent,
					silentIconTitle: t('spreed', 'Will be sent without notification'),
				}
			}

			return {
				showCommonReadIcon: this.showCommonReadIcon,
				commonReadIconTitle: t('spreed', 'Message read by everyone who shares their reading status'),
				showSentIcon: this.showSentIcon,
				sentIconTitle: t('spreed', 'Message sent'),
				showSilentIcon: this.message.silent,
				silentIconTitle: t('spreed', 'Sent without notification'),
			}
		},

		canReact() {
			return this.conversation.readOnly !== CONVERSATION.STATE.READ_ONLY
				&& (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT) !== 0
				&& this.message.messageType !== MESSAGE.TYPE.COMMAND
				&& this.message.messageType !== MESSAGE.TYPE.COMMENT_DELETED
		},

		buttonsBarOutlined() {
			return !this.isSplitViewEnabled
				|| (this.isReactionsMenuOpen || this.isSmallMobile || this.isSidebar)
		},

		isThreadStarterMessage() {
			if (this.threadId || !this.message.isThread) {
				return false
			}

			return this.message.id === this.message.threadId
				|| (this.message.threadTitle && this.message.id.toString().startsWith('temp-'))
				// FIXME properly render scheduled messages as threads
				|| (this.message.threadTitle && this.message.threadId === -1)
		},

		isShortSimpleMessage() {
			return this.message.message.length <= 20 // FIXME: magic number
				&& !this.message.parent
				&& !this.isThreadStarterMessage
				&& Object.keys(this.message.messageParameters).length === 0
				&& Object.keys(this.message.reactions).length === 0
				&& this.message.message.split('\n').length === 1
		},

		isPinned() {
			return !!this.message.metaData?.pinnedAt
		},
	},

	methods: {
		t,

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
					showWarning(t('spreed', 'Message deleted successfully, but a bot or Matterbridge is configured and the message might already be distributed to other services'), {
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

		toggleFollowUpEmojiPicker() {
			this.isFollowUpEmojiPickerOpen = !this.isFollowUpEmojiPickerOpen
		},
	},
}
</script>

<style lang="scss" scoped>
.message {
	position: relative;

	&:not(.message--deleted):hover > .message-body,
	&--hovered .normal:not(.message--deleted) > .message-body {
		background-color: var(--message-body-hover, var(--color-background-hover));
	}

	&:not(.message--sided) {
		.message-body__scroll {
			height: 100%;
			top: 0;
			inset-inline-end: 0;
			padding-top: var(--default-grid-baseline);
			padding-inline-end: var(--default-grid-baseline);
		}
	}

	// BEGIN Split view
	&--sided {
		--buttons-bar-min-width: calc(3 * var(--default-clickable-area) + 2 * var(--default-grid-baseline));
		width: fit-content;
		max-width: min(90%, calc(100% - var(--buttons-bar-min-width)));

		&.message--small-view {
			max-width: 90%;
		}

		// Shared styles for message bubbles
		.message-body {
			border: 1px solid var(--color-primary-element-light-hover);
		}

		.message-body__scroll {
			--overlay-offset: 100%;
			display: flex;
			align-items: center;
			top: 0;
			padding-inline: var(--default-grid-baseline);
			min-width: var(--buttons-bar-min-width);
			height: 100%;

			&.overlay {
				--overlay-offset: max(100% - var(--default-clickable-area) * 4, var(--default-clickable-area) * 4 - 100%);
			}

			&.bottom-side {
				top: unset !important;
				inset-inline-start: unset !important;
				bottom: 0;
				padding-block-end: var(--default-grid-baseline);
				height: fit-content;
				pointer-events: none;

				& > * {
					pointer-events: auto;
				}
			}
		}

		.icon-pin-highlighted {
			display: flex;
			color: var(--color-main-background);
			background-color: var(--color-primary-element);
			border-radius: 50%;
			margin: calc(var(--default-grid-baseline) + 1px);
			padding: var(--default-grid-baseline);
			z-index: 1;
		}

		&.outgoing {
			align-self: flex-end;

			.message-buttons-bar:not(.outlined) {
				flex-direction: row-reverse;
			}

			.message-body {
				--message-body-hover: var(--color-primary-light-hover);
				background-color: var(--color-primary-light);
				border-start-end-radius: var(--border-radius-small);
			}

			.message-body__scroll {
				justify-content: flex-end;
				inset-inline-end: var(--overlay-offset);

				&.bottom-side {
					inset-inline-end: auto;
				}
			}
		}

		&.incoming {
			align-self: flex-start;

			.message-body {
				--message-body-hover: color(from var(--color-primary-element-light-hover) srgb r g b / 0.45);
				background-color: color(from var(--color-primary-element-light) srgb r g b / 0.45);
				border-start-start-radius: var(--border-radius-small);
			}

			.message-body__scroll {
				inset-inline-start: var(--overlay-offset);

				&.bottom-side {
					inset-inline-end: 0;
				}
			}
		}

	}
	// END Split view

	&--pinned {
		color: var(--color-text-light);
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius-large);
		margin-bottom: 2px
	}
}

.message-body {
	padding: var(--default-grid-baseline);
	font-size: var(--default-font-size);
	line-height: var(--default-line-height);
	position: relative;
	border-radius: var(--border-radius-large);

	&__scroll {
		position: absolute;
		width: fit-content;
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

.message-buttons-bar {
	display: flex;
	inset-inline-end: 14px;
	top: 0;
	position: sticky;
	height: var(--default-clickable-area);
	z-index: 1;

	&.outlined {
		background-color: var(--color-main-background);
		border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
		box-shadow: 0 0 4px 0 var(--color-box-shadow);
	}
}
</style>
