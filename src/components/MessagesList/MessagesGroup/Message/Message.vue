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
		tabindex="0"
		@mouseover="handleMouseover"
		@mouseleave="handleMouseleave">
		<div :class="{'normal-message-body': !isSystemMessage && !isDeletedMessage, 'system' : isSystemMessage}"
			class="message-body">
			<div v-if="isFirstMessage && showAuthor"
				class="message-body__author"
				aria-level="4">
				{{ actorDisplayName }}
			</div>
			<div ref="messageMain"
				class="message-body__main">
				<div v-if="isSingleEmoji"
					class="message-body__main__text">
					<Quote v-if="parent" :parent-id="parent" v-bind="quote" />
					<div class="single-emoji">
						{{ message }}
					</div>
				</div>
				<div v-else-if="showJoinCallButton" class="message-body__main__text call-started">
					<RichText :text="message"
						:arguments="richParameters"
						:autolink="true"
						:reference-limit="0" />
					<CallButton />
				</div>
				<div v-else-if="showResultsButton" class="message-body__main__text system-message">
					<RichText :text="message"
						:arguments="richParameters"
						:autolink="true"
						:reference-limit="0" />
					<!-- Displays only the "see results" button with the results modal -->
					<Poll :id="messageParameters.poll.id"
						:poll-name="messageParameters.poll.name"
						:token="token"
						:show-as-button="true" />
				</div>
				<div v-else-if="isDeletedMessage" class="message-body__main__text deleted-message">
					<RichText :text="message"
						:arguments="richParameters"
						:autolink="true"
						:reference-limit="0" />
				</div>
				<div v-else class="message-body__main__text" :class="{'system-message': isSystemMessage}">
					<Quote v-if="parent" :parent-id="parent" v-bind="quote" />
					<RichText :text="message"
						:arguments="richParameters"
						:autolink="true"
						:reference-limit="1" />
				</div>
				<div v-if="!isDeletedMessage" class="message-body__main__right">
					<span v-tooltip.auto="messageDate"
						class="date"
						:style="{'visibility': hasDate ? 'visible' : 'hidden'}"
						:class="{'date--self': showSentIcon}">{{ messageTime }}</span>

					<!-- Message delivery status indicators -->
					<div v-if="sendingFailure"
						v-tooltip.auto="sendingErrorIconTooltip"
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
						v-tooltip.auto="loadingIconTooltip"
						class="icon-loading-small message-status"
						:aria-label="loadingIconTooltip" />
					<div v-else-if="showCommonReadIcon"
						v-tooltip.auto="commonReadIconTooltip"
						class="message-status"
						:aria-label="commonReadIconTooltip">
						<CheckAll :size="16" />
					</div>
					<div v-else-if="showSentIcon"
						v-tooltip.auto="sentIconTooltip"
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
				<NcPopover v-for="reaction in Object.keys(simpleReactions)"
					:key="reaction"
					:delay="200"
					:triggers="['hover']">
					<NcButton v-if="simpleReactions[reaction] !== 0"
						slot="trigger"
						:type="userHasReacted(reaction) ? 'primary' : 'secondary'"
						class="reaction-button"
						@click="handleReactionClick(reaction)">
						{{ reaction }} {{ simpleReactions[reaction] }}
					</NcButton>
					<div v-if="detailedReactions" class="reaction-details">
						<span>{{ getReactionSummary(reaction) }}</span>
					</div>
				</NcPopover>

				<!-- More reactions picker -->
				<NcEmojiPicker v-if="canReact && showMessageButtonsBar"
					:per-line="5"
					:container="`#message_${id}`"
					@select="handleReactionClick">
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
		<MessageButtonsBar v-if="showMessageButtonsBar"
			ref="messageButtonsBar"
			:is-action-menu-open.sync="isActionMenuOpen"
			:is-emoji-picker-open.sync="isEmojiPickerOpen"
			:is-reactions-menu-open.sync="isReactionsMenuOpen"
			:is-forwarder-open.sync="isForwarderOpen"
			:message-api-data="messageApiData"
			:message-object="messageObject"
			:is-last-read="isLastReadMessage"
			:can-react="canReact"
			v-bind="$props"
			:previous-message-id="previousMessageId"
			:participant="participant"
			@delete="handleDelete" />
		<div v-if="isLastReadMessage"
			v-observe-visibility="lastReadMessageVisibilityChanged">
			<div class="new-message-marker">
				<span>{{ t('spreed', 'Unread messages') }}</span>
			</div>
		</div>
	</li>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import CallButton from '../../../TopBar/CallButton.vue'
import DeckCard from './MessagePart/DeckCard.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import FilePreview from './MessagePart/FilePreview.vue'
import Mention from './MessagePart/Mention.vue'
import RichText from '@nextcloud/vue-richtext'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Check from 'vue-material-design-icons/Check.vue'
import CheckAll from 'vue-material-design-icons/CheckAll.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import Quote from '../../../Quote.vue'
import isInCall from '../../../../mixins/isInCall.js'
import participant from '../../../../mixins/participant.js'
import { EventBus } from '../../../../services/EventBus.js'
import emojiRegex from 'emoji-regex/index.js'
import moment from '@nextcloud/moment'
import Location from './MessagePart/Location.vue'
import Contact from './MessagePart/Contact.vue'
import MessageButtonsBar from './MessageButtonsBar/MessageButtonsBar.vue'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import { showError, showSuccess, showWarning, TOAST_DEFAULT_TIMEOUT } from '@nextcloud/dialogs'
import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../../../../constants.js'
import Poll from './MessagePart/Poll.vue'

export default {
	name: 'Message',

	directives: {
		tooltip: Tooltip,
	},

	components: {
		NcButton,
		CallButton,
		Quote,
		RichText,
		AlertCircle,
		Check,
		CheckAll,
		Reload,
		MessageButtonsBar,
		NcEmojiPicker,
		EmoticonOutline,
		NcPopover,
		Poll,
	},

	mixins: [
		participant,
		isInCall,
	],

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
		 * The display name of the sender of the message.
		 */
		actorDisplayName: {
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
		 * If true, it displays the message author on top of the message.
		 */
		showAuthor: {
			type: Boolean,
			default: true,
		},
		/**
		 * Specifies if the message is temporary in order to display the spinner instead
		 * of the message time.
		 */
		isTemporary: {
			type: Boolean,
			required: true,
		},
		/**
		 * Specifies if the message is the first of a group of same-author messages.
		 */
		isFirstMessage: {
			type: Boolean,
			required: true,
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
		 * The type of the message.
		 */
		messageType: {
			type: String,
			required: true,
		},
		/**
		 * The parent message's id.
		 */
		parent: {
			type: Number,
			default: 0,
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

		lastReadMessageId: {
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

	data() {
		return {
			isHovered: false,
			// Is tall enough for both actions and date upon hovering
			isTallEnough: false,
			showReloadButton: false,
			isDeleting: false,
			// whether the message was seen, only used if this was marked as last read message
			seen: false,
			isActionMenuOpen: false,
			isEmojiPickerOpen: false,
			isReactionsMenuOpen: false,
			isForwarderOpen: false,
			detailedReactionsLoading: false,
		}
	},

	computed: {
		isLastReadMessage() {
			if (!this.nextMessageId) {
				// never display indicator on the very last message
				return false
			}
			// note: not reading lastReadMessage from the conversation as we want to define it externally
			// to have closer control on marker's visibility behavior
			return this.id === this.lastReadMessageId
				&& (!this.conversation.lastMessage
				|| this.id !== this.conversation.lastMessage.id)
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

		quote() {
			return this.parent && this.$store.getters.message(this.token, this.parent)
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

			// eslint-disable-next-line no-cond-assign
			while (match = regex.exec(this.message)) {
				if (emojiCount > 2) {
					return false
				}

				emojiStrings += match[0]
				emojiCount++
			}

			return emojiStrings === this.message
		},

		richParameters() {
			const richParameters = {}
			Object.keys(this.messageParameters).forEach(function(p) {
				const type = this.messageParameters[p].type
				const mimetype = this.messageParameters[p].mimetype
				if (type === 'user' || type === 'call' || type === 'guest') {
					richParameters[p] = {
						component: Mention,
						props: this.messageParameters[p],
					}
				} else if (type === 'file' && mimetype !== 'text/vcard') {
					const parameters = this.messageParameters[p]
					parameters['is-voice-message'] = this.messageType === 'voice-message'
					richParameters[p] = {
						component: FilePreview,
						props: parameters,
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
					// vue instances so we cannot pass that into the component
					// as a prop. Therefore we rename it into pollName
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
			if (this.isTemporary || this.isDeleting || this.sendingFailure) {
				// Never on temporary or failed messages
				return false
			}

			return this.isSystemMessage || !this.isHovered || this.isTallEnough
		},

		showMessageButtonsBar() {
			return !this.isSystemMessage && !this.isTemporary && (this.isHovered || this.isActionMenuOpen || this.isEmojiPickerOpen || this.isReactionsMenuOpen || this.isForwarderOpen)
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
			return this.$store.getters.hasReactions(this.token, this.id)
		},

		canReact() {
			return this.conversation.readOnly !== CONVERSATION.STATE.READ_ONLY
				&& (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT) !== 0
				&& this.messageObject.messageType !== 'command'
				&& this.messageObject.messageType !== 'comment_deleted'
		},

		simpleReactions() {
			return this.messageObject.reactions
		},

		detailedReactions() {
			return this.$store.getters.reactions(this.token, this.id)
		},

		detailedReactionsLoaded() {
			return this.$store.getters.reactionsLoaded(this.token, this.id)
		},
	},

	watch: {
		showJoinCallButton() {
			EventBus.$emit('scroll-chat-to-bottom')
		},
	},

	mounted() {
		if (this.$refs.messageMain.clientHeight > 56) {
			this.isTallEnough = true
		}

		// define a function so it can be triggered directly on the DOM element
		// which can be found with document.getElementById()
		this.$refs.message.highlightAnimation = () => {
			this.highlightAnimation()
		}

		this.$refs.message.addEventListener('animationend', this.highlightAnimationStop)
	},

	beforeDestroy() {
		this.$refs.message.removeEventListener('animationend', this.highlightAnimationStop)
	},

	methods: {
		userHasReacted(reaction) {
			return this.reactionsSelf && this.reactionsSelf.indexOf(reaction) !== -1
		},

		lastReadMessageVisibilityChanged(isVisible) {
			if (isVisible) {
				this.seen = true
			}
		},

		highlightAnimation() {
			// trigger CSS highlight animation by setting a class
			this.$refs.message.classList.add('highlight-animation')
		},
		highlightAnimationStop() {
			// when the animation ended, remove the class so we can trigger it
			// again another time
			this.$refs.message.classList.remove('highlight-animation')
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
				return this.$store.getters.getGuestNameWithGuestSuffix(this.token, reaction.actorId)
			}

			if (displayName === '') {
				return t('spreed', 'Deleted user')
			}

			return displayName
		},
	},
}
</script>

<style lang="scss" scoped>
@import '@nextcloud/vue-richtext/dist/style.css';
@import '../../../../assets/variables';

.message:hover .normal-message-body {
	border-radius: 8px;
	background-color: var(--color-background-hover);
}

.message {
	position: relative;
}

.message-body {
	padding: 4px;
	font-size: $chat-font-size;
	line-height: $chat-line-height;
	position: relative;
	&__author {
		color: var(--color-text-maxcontrast);
	}
	&__main {
		display: flex;
		justify-content: space-between;
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
				background-color: var(--color-primary-light);
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

			::v-deep .rich-text--wrapper {
				white-space: pre-wrap;
				word-break: break-word;
			}

			&--quote {
				border-left: 4px solid var(--color-primary);
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
			font-size: $chat-font-size;
			flex: 1 0 auto;
			padding: 0 8px 0 8px;
		}
	}
	&__reactions {
		display: flex;
		flex-wrap: wrap;
		margin: 4px 175px 4px -2px;
	}
}

.date {
	margin-right: $clickable-area;
	&--self {
		margin-right: 0;
	}
}

// Increase the padding for regular messages to improve readability and
// allow some space for the reply button
.message-body:not(.system) {
	padding: 4px 4px 4px 8px;
}

.highlight-animation {
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
	margin: 20px 15px 20px -45px;
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
	margin: -8px 0;
	width: $clickable-area;
	height: $clickable-area;
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
	::v-deep .button-vue__text {
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
</style>
