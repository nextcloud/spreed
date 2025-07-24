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
		:class="{ 'message--hovered': showMessageButtonsBar }"
		tabindex="0"
		@animationend="clearHighlightedClass"
		@mouseover="handleMouseover"
		@mouseleave="handleMouseleave">
		<div :class="{
				'normal-message-body': !isSystemMessage && !isDeletedMessage,
				system: isSystemMessage,
				'combined-system': isCombinedSystemMessage,
			}"
			class="message-body">
			<MessageBody :rich-parameters="richParameters"
				:is-deleting="isDeleting"
				:has-call="conversation.hasCall"
				:message="message"
				:read-info="readInfo">
				<!-- reactions buttons and popover with details -->
				<Reactions v-if="Object.keys(message.reactions).length"
					:id="message.id"
					:token="message.token"
					:can-react="canReact"
					:show-controls="isHovered || isFollowUpEmojiPickerOpen"
					@emoji-picker-toggled="toggleFollowUpEmojiPicker" />
			</MessageBody>
		</div>

		<!-- Message actions -->
		<div class="message-body__scroll">
			<MessageButtonsBar v-if="showMessageButtonsBar"
				ref="messageButtonsBar"
				v-model:is-action-menu-open="isActionMenuOpen"
				v-model:is-emoji-picker-open="isEmojiPickerOpen"
				v-model:is-reactions-menu-open="isReactionsMenuOpen"
				v-model:is-forwarder-open="isForwarderOpen"
				class="message-buttons-bar"
				:is-translation-available="isTranslationAvailable"
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
				<NcButton variant="tertiary"
					:aria-label="t('spreed', 'Show or collapse system messages')"
					:title="t('spreed', 'Show or collapse system messages')"
					@click="toggleCombinedSystemMessage">
					<template #icon>
						<IconUnfoldMore v-if="isCombinedSystemMessageCollapsed" />
						<IconUnfoldLess v-else />
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
			v-intersection-observer="lastReadMessageVisibilityChanged"
			class="message-unread-marker">
			<div class="message-unread-marker__wrapper">
				<span class="message-unread-marker__text">{{ t('spreed', 'Unread messages') }}</span>
				<NcButton v-if="shouldShowSummaryOption"
					:disabled="loading"
					@click="generateSummary">
					<template #icon>
						<NcLoadingIcon v-if="loading" />
						<IconCreation v-else />
					</template>
					{{ t('spreed', 'Generate summary') }}
				</NcButton>
			</div>
		</div>
	</li>
</template>

<script>
import { showError, showSuccess, showWarning, TOAST_DEFAULT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { vIntersectionObserver as IntersectionObserver } from '@vueuse/components'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconCreation from 'vue-material-design-icons/Creation.vue'
import IconUnfoldLess from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import IconUnfoldMore from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
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
import { CONVERSATION, MENTION, MESSAGE, PARTICIPANT } from '../../../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../../../services/CapabilitiesManager.ts'
import { EventBus } from '../../../../services/EventBus.ts'
import { useActorStore } from '../../../../stores/actor.ts'
import { useChatExtrasStore } from '../../../../stores/chatExtras.ts'
import { getItemTypeFromMessage } from '../../../../utils/getItemTypeFromMessage.ts'

const canSummarizeChat = hasTalkFeature('local', 'chat-summary-api')
const summaryThreshold = getTalkConfig('local', 'chat', 'summary-threshold') ?? 0

export default {
	name: 'Message',

	components: {
		IconCreation,
		IconUnfoldLess,
		IconUnfoldMore,
		MessageBody,
		MessageButtonsBar,
		MessageForwarder,
		MessageTranslateDialog,
		NcButton,
		NcLoadingIcon,
		Reactions,
	},

	directives: {
		IntersectionObserver,
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

	emits: ['toggleCombinedSystemMessage'],

	setup(props) {
		const isTranslationAvailable = getTalkConfig(props.token, 'chat', 'has-translation-providers')
			// Fallback for the desktop client when connecting to Talk 17
			?? getTalkConfig(props.token, 'chat', 'translations')?.length > 0

		return {
			isTranslationAvailable,
			chatExtrasStore: useChatExtrasStore(),
			actorStore: useActorStore(),
		}
	},

	data() {
		return {
			loading: false,
			isHovered: false,
			isDeleting: false,
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

			if (this.message.id === this.visualLastLastReadMessageId) {
				return !this.isCollapsedSystemMessage || this.message.id !== this.lastCollapsedMessageId
			}

			return this.isCombinedSystemMessage && this.lastCollapsedMessageId === this.visualLastLastReadMessageId
		},

		shouldShowSummaryOption() {
			if (this.conversation.remoteServer || !canSummarizeChat || this.chatExtrasStore.hasChatSummaryTaskRequested(this.message.token)) {
				return false
			}
			return (this.conversation.unreadMessages >= summaryThreshold)
		},

		isSystemMessage() {
			return this.message.systemMessage !== ''
		},

		isDeletedMessage() {
			return this.message.messageType === MESSAGE.TYPE.COMMENT_DELETED
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
				&& this.actorStore.checkIfSelfIsActor(this.message)
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
						component: Mention,
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
	},

	methods: {
		t,
		lastReadMessageVisibilityChanged([{ isIntersecting }]) {
			if (isIntersecting) {
				this.seen = true
			}
		},

		clearHighlightedClass() {
			this.$refs.message.classList.remove('message--highlighted')
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

		toggleCombinedSystemMessage() {
			this.$emit('toggleCombinedSystemMessage')
		},

		toggleFollowUpEmojiPicker() {
			this.isFollowUpEmojiPickerOpen = !this.isFollowUpEmojiPickerOpen
		},

		async generateSummary() {
			this.loading = true
			await this.chatExtrasStore.requestChatSummary(this.message.token, this.message.id)
			this.loading = false
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
	font-size: var(--default-font-size);
	line-height: var(--default-line-height);
	position: relative;

	&__scroll {
		position: absolute;
		top: 0;
		inset-inline-end: 0;
		width: fit-content;
		height: 100%;
		padding-top: var(--default-grid-baseline);
		padding-inline-end: var(--default-grid-baseline);
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

.message-unread-marker {
	position: relative;
	margin: calc(4 * var(--default-grid-baseline));

	&::before {
		content: '';
		width: 100%;
		border-top: 1px solid var(--color-border-maxcontrast);
		position: absolute;
		top: 50%;
		z-index: -1;
	}

	&__wrapper {
		display: flex;
		justify-content: center;
		align-items: center;
		gap: calc(3 * var(--default-grid-baseline));
		margin-inline: auto;
		padding-inline: calc(3 * var(--default-grid-baseline));
		width: fit-content;
		border-radius: var(--border-radius);
		background-color: var(--color-main-background);
	}

	&__text {
		text-align: center;
		white-space: nowrap;
		font-weight: bold;
		color: var(--color-main-text);
	}
}

.message-buttons-bar {
	display: flex;
	inset-inline-end: 14px;
	top: 0;
	position: sticky;
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
	box-shadow: 0 0 4px 0 var(--color-box-shadow);
	height: var(--default-clickable-area);
	z-index: 1;
}
</style>
