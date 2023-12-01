<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
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

<docs>
This component displays the text inside the message component and can be used for
the main body of the message as well as a quote.
</docs>

<template>
	<li :id="`message_${id}`"
		ref="message"
		:data-message-id="id"
		:data-seen="seen"
		:data-next-message-id="nextMessageId"
		:data-previous-message-id="previousMessageId"
		class="message"
		:class="{'message--highlighted': isHighlighted}"
		tabindex="0"
		@animationend="isHighlighted = false"
		@mouseover="handleMouseover"
		@mouseleave="handleMouseleave">
		<div :class="{'normal-message-body': !isSystemMessage && !isDeletedMessage,
			'system' : isSystemMessage,
			'combined-system': isCombinedSystemMessage}"
			class="message-body">
			<div ref="messageMain"
				class="message-body__main">
				<div v-if="isSingleEmoji"
					class="message-body__main__text">
					<Quote v-if="parent" v-bind="parent" />
					<div class="single-emoji">
						{{ message }}
					</div>
				</div>
				<div v-else-if="showJoinCallButton" class="message-body__main__text call-started">
					<NcRichText :text="message"
						:arguments="richParameters"
						autolink
						:reference-limit="0" />
					<CallButton />
				</div>
				<div v-else-if="showResultsButton || isSystemMessage" class="message-body__main__text system-message">
					<NcRichText :text="message"
						:arguments="richParameters"
						autolink
						:reference-limit="0" />
					<!-- Displays only the "see results" button with the results modal -->
					<Poll v-if="showResultsButton"
						:id="messageParameters.poll.id"
						:poll-name="messageParameters.poll.name"
						:token="token"
						show-as-button />
				</div>
				<div v-else-if="isDeletedMessage" class="message-body__main__text deleted-message">
					<NcRichText :text="message"
						:arguments="richParameters"
						autolink
						:reference-limit="0" />
				</div>
				<div v-else
					class="message-body__main__text message-body__main__text--markdown"
					@mouseover="handleMarkdownMouseOver"
					@mouseleave="handleMarkdownMouseLeave">
					<Quote v-if="parent" v-bind="parent" />
					<NcRichText :text="message"
						:arguments="richParameters"
						autolink
						:use-markdown="markdown"
						:reference-limit="1" />

					<NcButton v-if="containsCodeBlocks"
						v-show="currentCodeBlock !== null"
						class="message-copy-code"
						type="tertiary"
						:aria-label="t('spreed', 'Copy code block')"
						:title="t('spreed', 'Copy code block')"
						:style="{top: copyButtonOffset}"
						@click="copyCodeBlock">
						<template #icon>
							<ContentCopy :size="16" />
						</template>
					</NcButton>
				</div>
				<div v-if="!isDeletedMessage" class="message-body__main__right">
					<span :title="messageDate"
						class="date"
						:style="{'visibility': hasDate ? 'visible' : 'hidden'}"
						:class="{'date--self': showSentIcon}">{{ messageTime }}</span>

					<!-- Message delivery status indicators -->
					<div v-if="sendingFailure"
						:title="sendingErrorIconTooltip"
						class="message-status sending-failed"
						:class="{'retry-option': sendingErrorCanRetry}"
						:aria-label="sendingErrorIconTooltip"
						tabindex="0"
						@mouseover="showReloadButton = true"
						@focus="showReloadButton = true"
						@mouseleave="showReloadButton = true"
						@blur="showReloadButton = true">
						<NcButton v-if="sendingErrorCanRetry && showReloadButton"
							:aria-label="sendingErrorIconTooltip"
							@click="handleRetry">
							<template #icon>
								<Reload :size="16" />
							</template>
						</NcButton>
						<AlertCircle v-else
							:size="16" />
					</div>
					<div v-else-if="isTemporary && !isTemporaryUpload || isDeleting"
						:title="loadingIconTooltip"
						class="icon-loading-small message-status"
						:aria-label="loadingIconTooltip" />
					<div v-else-if="showCommonReadIcon"
						:title="commonReadIconTooltip"
						class="message-status"
						:aria-label="commonReadIconTooltip">
						<CheckAll :size="16" />
					</div>
					<div v-else-if="showSentIcon"
						:title="sentIconTooltip"
						class="message-status"
						:aria-label="sentIconTooltip">
						<Check :size="16" />
					</div>
				</div>
			</div>

			<!-- reactions buttons and popover with details -->
			<div v-if="hasReactions"
				class="message-body__reactions"
				@mouseover="handleReactionsMouseOver">
				<NcPopover v-for="reaction in Object.keys(reactions)"
					:key="reaction"
					:delay="200"
					:focus-trap="false"
					:triggers="['hover']">
					<template #trigger>
						<NcButton v-if="reactions[reaction] !== 0"
							:type="userHasReacted(reaction) ? 'primary' : 'secondary'"
							class="reaction-button"
							@click="handleReactionClick(reaction)">
							{{ reaction }} {{ reactions[reaction] }}
						</NcButton>
					</template>

					<div v-if="detailedReactions" class="reaction-details">
						<span>{{ getReactionSummary(reaction) }}</span>
					</div>
				</NcPopover>

				<!-- More reactions picker -->
				<NcEmojiPicker v-if="canReact && showMessageButtonsBar"
					:per-line="5"
					:container="`#message_${id}`"
					@select="handleReactionClick"
					@after-show="onEmojiPickerOpen"
					@after-hide="onEmojiPickerClose">
					<NcButton class="reaction-button"
						:aria-label="t('spreed', 'Add more reactions')">
						<template #icon>
							<EmoticonOutline :size="15" />
						</template>
					</NcButton>
				</NcEmojiPicker>
				<NcButton v-else-if="canReact"
					class="reaction-button"
					:aria-label="t('spreed', 'Add more reactions')">
					<template #icon>
						<EmoticonOutline :size="15" />
					</template>
				</NcButton>
			</div>
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
				:message-api-data="messageApiData"
				:message-object="messageObject"
				:can-react="canReact"
				v-bind="$props"
				:previous-message-id="previousMessageId"
				:participant="participant"
				:show-common-read-icon="showCommonReadIcon"
				:common-read-icon-tooltip="commonReadIconTooltip"
				:show-sent-icon="showSentIcon"
				:sent-icon-tooltip="sentIconTooltip"
				@show-translate-dialog="isTranslateDialogOpen = true"
				@reply="handleReply"
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

		<MessageTranslateDialog v-if="isTranslateDialogOpen"
			:message="message"
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
import emojiRegex from 'emoji-regex/index.js'

import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Check from 'vue-material-design-icons/Check.vue'
import CheckAll from 'vue-material-design-icons/CheckAll.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import UnfoldLess from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import UnfoldMore from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError, showSuccess, showWarning, TOAST_DEFAULT_TIMEOUT } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'

import Quote from '../../../Quote.vue'
import CallButton from '../../../TopBar/CallButton.vue'
import MessageButtonsBar from './MessageButtonsBar/MessageButtonsBar.vue'
import MessageTranslateDialog from './MessageButtonsBar/MessageTranslateDialog.vue'
import Contact from './MessagePart/Contact.vue'
import DeckCard from './MessagePart/DeckCard.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import FilePreview from './MessagePart/FilePreview.vue'
import Location from './MessagePart/Location.vue'
import Mention from './MessagePart/Mention.vue'
import Poll from './MessagePart/Poll.vue'

import { useIsInCall } from '../../../../composables/useIsInCall.js'
import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../../../constants.js'
import participant from '../../../../mixins/participant.js'
import { EventBus } from '../../../../services/EventBus.js'
import { useGuestNameStore } from '../../../../stores/guestName.js'
import { getItemTypeFromMessage } from '../../../../utils/getItemTypeFromMessage.js'

const isTranslationAvailable = getCapabilities()?.spreed?.config?.chat?.translations?.length > 0

/**
 * @property {object} scrollerBoundingClientRect provided by MessageList.vue
 */
export default {
	name: 'Message',

	components: {
		MessageTranslateDialog,
		CallButton,
		MessageButtonsBar,
		NcButton,
		NcEmojiPicker,
		NcPopover,
		NcRichText,
		Poll,
		Quote,
		// Icons
		AlertCircle,
		Check,
		CheckAll,
		ContentCopy,
		EmoticonOutline,
		Reload,
		UnfoldLess,
		UnfoldMore,
	},

	mixins: [
		participant,
	],

	inject: ['scrollerBoundingClientRect'],

	inheritAttrs: false,

	props: {
		/**
		 * The actor type of the sender of the message.
		 */
		actorType: {
			type: String,
			required: true,
		},
		/**
		 * The actor id of the sender of the message.
		 */
		actorId: {
			type: String,
			required: true,
		},
		/**
		 * The message or quote text.
		 */
		message: {
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
		 * Specifies if the message is temporary in order to display the spinner instead
		 * of the message time.
		 */
		isTemporary: {
			type: Boolean,
			default: false,
		},
		/**
		 * Specifies if the message can be replied to.
		 */
		isReplyable: {
			type: Boolean,
			required: true,
		},
		/**
		 * The conversation token.
		 */
		token: {
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
		 * The type of the message.
		 */
		messageType: {
			type: String,
			required: true,
		},
		/**
		 * The parent message.
		 */
		parent: {
			type: Object,
			default: undefined,
		},
		/**
		 * Is message allowed to render in markdown
		 */
		markdown: {
			type: Boolean,
			default: true,
		},
		sendingFailure: {
			type: String,
			default: '',
		},

		previousMessageId: {
			type: [String, Number],
			default: 0,
		},

		nextMessageId: {
			type: [String, Number],
			default: 0,
		},

		reactions: {
			type: [Array, Object],
			default: () => { return {} },
		},

		reactionsSelf: {
			type: Array,
			default: () => { return [] },
		},
	},

	emits: ['toggle-combined-system-message'],

	setup() {
		const isInCall = useIsInCall()
		const guestNameStore = useGuestNameStore()
		return { isInCall, isTranslationAvailable, guestNameStore }
	},

	expose: ['highlightMessage'],

	data() {
		return {
			isHovered: false,
			showReloadButton: false,
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
			detailedReactionsLoading: false,
			isTranslateDialogOpen: false,
			codeBlocks: null,
			currentCodeBlock: null,
			copyButtonOffset: 0,
		}
	},

	computed: {
		isLastMessage() {
			// never displayed for the very last message
			return !this.nextMessageId || this.id === this.conversation?.lastMessage?.id
		},

		isLastReadMessage() {
			return !this.isLastMessage && this.id === this.$store.getters.getVisualLastReadMessageId(this.token)
		},

		messageObject() {
			return this.$store.getters.message(this.token, this.id)
		},

		isSystemMessage() {
			return this.systemMessage !== ''
		},

		isDeletedMessage() {
			return this.messageType === 'comment_deleted'
		},

		messageTime() {
			return moment(this.timestamp * 1000).format('LT')
		},

		messageDate() {
			return moment(this.timestamp * 1000).format('LL')
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		showCommonReadIcon() {
			return this.conversation.lastCommonReadMessage >= this.id
				&& this.showSentIcon && !this.isDeletedMessage
		},

		showSentIcon() {
			return !this.isSystemMessage
				&& !this.isTemporary
				&& !this.isDeleting
				&& this.actorType === this.participant.actorType
				&& this.actorId === this.participant.actorId
				&& !this.isDeletedMessage
		},

		messagesList() {
			return this.$store.getters.messagesList(this.token)
		},

		isLastCallStartedMessage() {
			// FIXME: remove dependency to messages list and convert to property
			const messages = this.messagesList
			// FIXME: don't reverse the whole array as it would create a copy, just do an actual reverse search
			const lastCallStartedMessage = messages.reverse().find((message) => message.systemMessage === 'call_started')
			return lastCallStartedMessage ? (this.id === lastCallStartedMessage.id) : false
		},

		showJoinCallButton() {
			return this.systemMessage === 'call_started'
				&& this.conversation.hasCall
				&& this.isLastCallStartedMessage
				&& !this.isInCall
		},

		showResultsButton() {
			return this.systemMessage === 'poll_closed'
		},

		isSingleEmoji() {
			const regex = emojiRegex()
			let match
			let emojiStrings = ''
			let emojiCount = 0
			const trimmedMessage = this.message.trim()

			// eslint-disable-next-line no-cond-assign
			while (match = regex.exec(trimmedMessage)) {
				if (emojiCount > 2) {
					return false
				}

				emojiStrings += match[0]
				emojiCount++
			}

			return emojiStrings === trimmedMessage
		},

		richParameters() {
			const richParameters = {}
			Object.keys(this.messageParameters).forEach(function(p) {
				const type = this.messageParameters[p].type
				const mimetype = this.messageParameters[p].mimetype
				const itemType = getItemTypeFromMessage(this.messageObject)
				if (type === 'user' || type === 'call' || type === 'guest' || type === 'user-group' || type === 'group') {
					richParameters[p] = {
						component: Mention,
						props: this.messageParameters[p],
					}
				} else if (type === 'file' && mimetype !== 'text/vcard') {
					richParameters[p] = {
						component: FilePreview,
						props: Object.assign({
							token: this.token,
							itemType,
						}, this.messageParameters[p]),
					}
				} else if (type === 'deck-card') {
					richParameters[p] = {
						component: DeckCard,
						props: this.messageParameters[p],
					}
				} else if (type === 'geo-location') {
					richParameters[p] = {
						component: Location,
						props: this.messageParameters[p],
					}
				} else if (type === 'talk-poll' && this.systemMessage !== 'poll_closed') {
					const props = Object.assign({}, this.messageParameters[p])
					// Add the token to the component props
					props.token = this.token
					// The word 'name' is reserved in for the component name in
					// Vue instances, so we cannot pass that into the component
					// as a prop, therefore we rename it into pollName
					props.pollName = this.messageParameters[p].name
					richParameters[p] = {
						component: Poll,
						props,
					}
				} else if (mimetype === 'text/vcard') {
					richParameters[p] = {
						component: Contact,
						props: this.messageParameters[p],
					}
				} else {
					richParameters[p] = {
						component: DefaultParameter,
						props: this.messageParameters[p],
					}
				}
			}.bind(this))
			return richParameters
		},

		// Determines whether the date has to be displayed or not
		hasDate() {
			return (!this.isTemporary && !this.isDeleting && !this.sendingFailure)
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

		isTemporaryUpload() {
			return this.isTemporary && this.messageParameters.file
		},

		loadingIconTooltip() {
			return t('spreed', 'Sending message')
		},

		sentIconTooltip() {
			return t('spreed', 'Message sent')
		},

		commonReadIconTooltip() {
			return t('spreed', 'Message read by everyone who shares their reading status')
		},

		sendingErrorCanRetry() {
			return this.sendingFailure === 'timeout' || this.sendingFailure === 'other'
		},

		sendingErrorIconTooltip() {
			if (this.sendingErrorCanRetry) {
				return t('spreed', 'Failed to send the message. Click to try again')
			}
			if (this.sendingFailure === 'quota') {
				return t('spreed', 'Not enough free space to upload file')
			}
			if (this.sendingFailure === 'failed-share') {
				return t('spreed', 'You are not allowed to share files')
			}
			return t('spreed', 'You cannot send messages to this conversation at the moment')
		},

		messageActions() {
			return this.$store.getters.messageActions
		},

		messageApiData() {
			return {
				message: this.messageObject,
				metadata: this.conversation,
				apiVersion: 'v3',
			}
		},

		hasReactions() {
			return Object.keys(this.reactions).length !== 0
		},

		canReact() {
			return this.conversation.readOnly !== CONVERSATION.STATE.READ_ONLY
				&& (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT) !== 0
				&& this.messageObject.messageType !== 'command'
				&& this.messageObject.messageType !== 'comment_deleted'
		},

		detailedReactions() {
			return this.$store.getters.reactions(this.token, this.id)
		},

		detailedReactionsLoaded() {
			return this.$store.getters.reactionsLoaded(this.token, this.id)
		},

		containsCodeBlocks() {
			return this.message.includes('```')
		},
	},

	watch: {
		showJoinCallButton() {
			EventBus.$emit('scroll-chat-to-bottom')
		},

		// Scroll list to the bottom if reaction to the message was added, as it expands the list
		reactions() {
			EventBus.$emit('scroll-chat-to-bottom-if-sticky')
		},
	},

	mounted() {
		if (!this.containsCodeBlocks) {
			return
		}

		this.codeBlocks = Array.from(this.$refs.message?.querySelectorAll('pre'))
	},

	methods: {
		handleMarkdownMouseOver(event) {
			if (!this.containsCodeBlocks) {
				return
			}

			const index = this.codeBlocks.findIndex(item => item.contains(event.target))
			if (index !== -1) {
				this.currentCodeBlock = index
				const el = this.codeBlocks[index]
				this.copyButtonOffset = `${el.offsetTop}px`
			}
		},

		handleMarkdownMouseLeave() {
			this.currentCodeBlock = null
			this.copyButtonOffset = 0
		},

		async copyCodeBlock() {
			const code = this.codeBlocks[this.currentCodeBlock].textContent
			try {
				await navigator.clipboard.writeText(code)
				showSuccess(t('spreed', 'Code block copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Code block could not be copied'))
			}
		},

		userHasReacted(reaction) {
			return this.reactionsSelf?.includes(reaction)
		},

		lastReadMessageVisibilityChanged(isVisible) {
			if (isVisible) {
				this.seen = true
			}
		},

		highlightMessage() {
			this.isHighlighted = true
		},

		handleRetry() {
			if (this.sendingErrorCanRetry) {
				EventBus.$emit('retry-message', this.id)
				EventBus.$emit('focus-chat-input')
			}
		},

		handleMouseover() {
			if (!this.isHovered) {
				this.isHovered = true
			}
		},

		handleReactionsMouseOver() {
			if (this.hasReactions && !this.detailedReactionsLoaded) {
				this.getReactions()
			}
		},

		handleMouseleave() {
			if (this.isHovered) {
				this.isHovered = false
			}
		},

		async getReactions() {
			if (this.detailedReactionsLoading) {
				// A parallel request is already doing this
				return
			}

			try {
				/**
				 * Get reaction details when the message is hovered for the first
				 * time. After that we rely on system messages to update the
				 * reactions.
				 */
				this.detailedReactionsLoading = true
				await this.$store.dispatch('getReactions', {
					token: this.token,
					messageId: this.id,
				})
				this.detailedReactionsLoading = false
			} catch {
				this.detailedReactionsLoading = false
			}
		},

		onEmojiPickerOpen() {
			this.isFollowUpEmojiPickerOpen = true
		},

		onEmojiPickerClose() {
			this.isFollowUpEmojiPickerOpen = false
		},

		async handleReactionClick(clickedEmoji) {
			if (!this.canReact) {
				showError(t('spreed', 'No permission to post reactions in this conversation'))
				return
			}

			// Check if current user has already added this reaction to the message
			if (!this.userHasReacted(clickedEmoji)) {
				this.$store.dispatch('addReactionToMessage', {
					token: this.token,
					messageId: this.id,
					selectedEmoji: clickedEmoji,
					actorId: this.actorId,
				})
			} else {
				this.$store.dispatch('removeReactionFromMessage', {
					token: this.token,
					messageId: this.id,
					selectedEmoji: clickedEmoji,
					actorId: this.actorId,
				})
			}
		},

		handleReply() {
			this.$store.dispatch('addMessageToBeReplied', {
				token: this.token,
				id: this.id,
			})
			EventBus.$emit('focus-chat-input')
		},

		async handleDelete() {
			this.isDeleting = true
			try {
				const statusCode = await this.$store.dispatch('deleteMessage', {
					message: {
						token: this.token,
						id: this.id,
					},
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

		getReactionSummary(reaction) {
			const list = this.detailedReactions[reaction]
			const summary = []

			for (const item in list) {
				if (list[item].actorType === this.$store.getters.getActorType()
					&& list[item].actorId === this.$store.getters.getActorId()) {
					summary.unshift(t('spreed', 'You'))
				} else {
					summary.push(this.getDisplayNameForReaction(list[item]))
				}
			}

			return summary.join(', ')
		},

		getDisplayNameForReaction(reaction) {
			const displayName = reaction.actorDisplayName.trim()

			if (reaction.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				return this.guestNameStore.getGuestNameWithGuestSuffix(this.token, reaction.actorId)
			}

			if (displayName === '') {
				return t('spreed', 'Deleted user')
			}

			return displayName
		},

		toggleCombinedSystemMessage() {
			this.$emit('toggle-combined-system-message')
		},
	},
}
</script>

<style lang="scss" scoped>
.message {
	position: relative;

	&:hover .normal-message-body,
	&:hover .combined-system {
		border-radius: 8px;
		background-color: var(--color-background-hover);
	}
}

.message-body {
	padding: 4px;
	font-size: var(--default-font-size);
	line-height: var(--default-line-height);
	position: relative;
	&__main {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		min-width: 100%;
		&__text {
			flex: 0 1 600px;
			width: 100%;
			min-width: 0;
			max-width: 600px;
			color: var(--color-text-light);
			.single-emoji {
				font-size: 250%;
				line-height: 100%;
			}

			&.call-started {
				background-color: var(--color-primary-element-light);
				padding: 10px;
				border-radius: var(--border-radius-large);
				text-align: center;
			}

			&.system-message {
				color: var(--color-text-maxcontrast);
				text-align: center;
				padding: 0 20px;
				width: 100%;
			}

			&.deleted-message {
				color: var(--color-text-lighter);
				display: flex;
				border-radius: var(--border-radius-large);
				align-items: center;
			}

			&--quote {
				border-left: 4px solid var(--color-primary-element);
				padding: 4px 0 0 8px;
			}
		}
		&__right {
			justify-self: flex-start;
			justify-content: flex-end;
			position: relative;
			user-select: none;
			display: flex;
			color: var(--color-text-maxcontrast);
			font-size: var(--default-font-size);
			flex: 1 0 auto;
			padding: 0 8px 0 8px;
		}
	}

	&__scroll {
		position: absolute;
		top: 0;
		right: 0;
		width: fit-content;
		height: 100%;
		padding: 8px 8px 0 0;
	}

	&__reactions {
		display: flex;
		flex-wrap: wrap;
		margin: 4px 175px 4px -2px;
	}
}

.date {
	margin-right: var(--default-clickable-area);
	&--self {
		margin-right: 0;
	}
}

// Increase the padding for regular messages to improve readability and
// allow some space for the reply button
.message-body:not(.system) {
	padding: 4px 4px 4px 8px;
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

		border-radius: var(--border-radius);
		background-color: var(--color-main-background);
	}
}

.message-status {
	width: var(--default-clickable-area);
	height: 24px;
	display: flex;
	justify-content: center;
	align-items: center;

	&.retry-option {
		cursor: pointer;
	}
}

.reaction-button {
	// Clear server rules
	min-height: 0 !important;
	:deep(.button-vue__text) {
		font-weight: normal !important;
	}

	margin: 2px;
	height: 26px;
	padding: 0 6px !important;

	&__emoji {
		margin: 0 4px 0 0;
	}
}

.reaction-details {
	padding: 8px;
}

.message-buttons-bar {
	display: flex;
	right: 14px;
	top: 8px;
	position: sticky;
	background-color: var(--color-main-background);
	border-radius: calc(var(--default-clickable-area) / 2);
	box-shadow: 0 0 4px 0 var(--color-box-shadow);
	height: 44px;
	z-index: 1;

	& h6 {
		margin-left: auto;
	}
}

.message-body__main__text--markdown {
  position: relative;

  .message-copy-code {
    position: absolute;
    top: 0;
    right: 4px;
    margin-top: 4px;
    background-color: var(--color-background-dark);
  }

	:deep(.rich-text--wrapper) {
		// Overwrite core styles, otherwise h4 is lesser than default font-size
		h4 {
			font-size: 100%;
		}

		em {
			font-style: italic;
		}

		pre {
			padding: 4px;
			margin: 2px 0;
			border-radius: var(--border-radius);
			background-color: var(--color-background-dark);
			overflow-x: auto;

			& code {
				margin: 0;
				padding: 0;
			}
		}

		code {
			display: inline-block;
			padding: 2px 4px;
			margin: 2px 0;
			border-radius: var(--border-radius);
			background-color: var(--color-background-dark);
		}

		blockquote {
			position: relative;
			border-left: none;

			&::before {
				content: ' ';
				position: absolute;
				top: 0;
				left: 0;
				height: 100%;
				width: 4px;
				border-radius: 2px;
				background-color: var(--color-border);
			}
		}
	}
}
</style>
