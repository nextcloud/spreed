<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
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

<docs>
This component displays the text inside the message component and can be used for
the main body of the message as well as a quote.
</docs>

<template>
	<div
		class="message"
		:class="{'hover': showActions && !isSystemMessage, 'system' : isSystemMessage}"
		@mouseover="showActions=true"
		@mouseleave="showActions=false">
		<div v-if="isFirstMessage && showAuthor" class="message__author">
			<h6>{{ actorDisplayName }}</h6>
		</div>
		<div
			ref="messageMain"
			class="message__main">
			<div v-if="isSingleEmoji"
				class="message__main__text">
				<Quote v-if="parent" v-bind="quote" />
				<div class="single-emoji">
					{{ message }}
				</div>
			</div>
			<div v-else-if="showJoinCallButton" class="message__main__text call-started">
				<RichText :text="message" :arguments="richParameters" :autolink="true" />
				<CallButton />
			</div>
			<div v-else class="message__main__text" :class="{'system-message': isSystemMessage}">
				<Quote v-if="parent" v-bind="quote" />
				<RichText :text="message" :arguments="richParameters" :autolink="true" />
			</div>
			<div class="message__main__right">
				<div v-if="isTemporary && !isTemporaryUpload" class="icon-loading-small" />
				<h6 v-if="hasDate">
					{{ messageTime }}
				</h6>
				<Actions
					v-show="showActions && hasActions"
					class="message__main__right__actions">
					<ActionButton
						v-if="isReplyable"
						icon="icon-reply"
						:close-after-click="true"
						@click.stop="handleReply">
						{{ t('spreed', 'Reply') }}
					</ActionButton>
				</Actions>
			</div>
		</div>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import CallButton from '../../../TopBar/CallButton'
import DefaultParameter from './MessagePart/DefaultParameter'
import FilePreview from './MessagePart/FilePreview'
import Mention from './MessagePart/Mention'
import RichText from '@juliushaertl/vue-richtext'
import Quote from '../../../Quote'
import { EventBus } from '../../../../services/EventBus'
import emojiRegex from 'emoji-regex'
import { PARTICIPANT } from '../../../../constants'
import moment from '@nextcloud/moment'

export default {
	name: 'Message',

	components: {
		Actions,
		ActionButton,
		CallButton,
		Quote,
		RichText,
	},
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
		 * The conversation token.
		 */
		systemMessage: {
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
	},

	data() {
		return {
			showActions: false,
			// Is tall enough for both actions and date upon hovering
			isTallEnough: false,
		}
	},

	computed: {
		hasActions() {
			return this.isReplyable
		},

		isSystemMessage() {
			return this.systemMessage !== ''
		},

		messageTime() {
			return moment(this.timestamp * 1000).format('LT')
		},
		quote() {
			return this.parent && this.$store.getters.message(this.token, this.parent)
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		participant() {
			const participantIndex = this.$store.getters.getParticipantIndex(this.token, this.$store.getters.getParticipantIdentifier())
			if (participantIndex !== -1) {
				return this.$store.getters.getParticipant(this.token, participantIndex)
			}

			return {
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			}
		},

		messagesList() {
			return this.$store.getters.messagesList(this.token)
		},

		isLastCallStartedMessage() {
			const messages = this.messagesList
			const lastCallStartedMessage = messages.reverse().find((message) => message.systemMessage === 'call_started')
			return lastCallStartedMessage ? (this.id === lastCallStartedMessage.id) : false
		},

		showJoinCallButton() {
			return this.systemMessage === 'call_started'
				&& this.conversation.hasCall
				&& this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED
				&& this.isLastCallStartedMessage
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
				if (type === 'user' || type === 'call' || type === 'guest') {
					richParameters[p] = {
						component: Mention,
						props: this.messageParameters[p],
					}
				} else if (type === 'file') {
					richParameters[p] = {
						component: FilePreview,
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
			return this.isSystemMessage || (!this.isTemporary && !this.showActions) || this.isTallEnough
		},

		isTemporaryUpload() {
			return this.isTemporary && this.messageParameters.file
		},
	},

	watch: {
		showJoinCallButton() {
			EventBus.$emit('scrollChatToBottom')
		},
	},

	mounted() {
		if (this.$refs.messageMain.clientHeight > 44) {
			this.isTallEnough = true
		}
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
			})
			EventBus.$emit('focusChatInput')
		},
		handleDelete() {
			this.$store.dispatch('deleteMessage', this.message)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../../assets/variables';

.message {
	padding: 4px;
	font-size: $chat-font-size;
	line-height: $chat-line-height;
	&__author {
		color: var(--color-text-maxcontrast);
	}
	&__main {
		display: flex;
		justify-content: space-between;
		min-width: 100%;
		&__text {
			flex: 1 1 auto;
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
			justify-content:  space-between;
			position: relative;
			display: flex;
			color: var(--color-text-maxcontrast);
			font-size: $chat-font-size;
			flex: 0 0 95px;
			padding: 0 8px 0 8px;
			&__actions.action-item {
				position: absolute;
				bottom: -11px;
				right: -3px;
			}
			& h6 {
				margin-left: auto;
			}
		}
	}
	.icon-loading-small {
		position: unset;
	}
}

// Increase the padding for regular messages to improve readability and
// allow some space for the reply button
.message:not(.system) {
	padding: 12px 4px 12px 8px;
	margin: -6px 0;
}

.hover {
	background-color: var(--color-background-hover);
	border-radius: 8px;
}
</style>
