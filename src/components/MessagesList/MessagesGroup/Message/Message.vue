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
		@mouseover="showActions=true"
		@mouseleave="showActions=false">
		<Quote v-if="parent" v-bind="quote" />
		<div v-if="isFirstMessage && showAuthor" class="message__author">
			<h6>{{ actorDisplayName }}</h6>
		</div>
		<div class="message__main">
			<div v-if="isSingleEmoji"
				class="message__main__text single-emoji">
				{{ message }}
			</div>
			<div v-else class="message__main__text">
				<component
					:is="getComponentInstanceForMessagePart(block.type)"
					v-for="(block, i) in parsedMessage"
					:key="i"
					:data="block.data" />
			</div>
			<div class="message__main__right">
				<div v-if="isTemporary" class="icon-loading-small" />
				<h6 v-else>
					{{ messageTime }}
				</h6>
				<Actions v-show="showActions" class="message__main__right__actions">
					<ActionButton
						icon="icon-reply"
						:close-after-click="true"
						@click.stop="handleReply">
						Reply
					</ActionButton>
					<ActionButton
						icon="icon-delete"
						:close-after-click="true"
						@click.stop="handleDelete">
						Delete
					</ActionButton>
				</Actions>
			</div>
		</div>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import DefaultParameter from './MessagePart/DefaultParameter'
import FilePreview from './MessagePart/FilePreview'
import Mention from './MessagePart/Mention'
import PlainText from './MessagePart/PlainText'
import Quote from '../../../Quote'
import emojiRegex from 'emoji-regex'

export default {
	name: 'Message',
	components: {
		Actions,
		ActionButton,
		DefaultParameter,
		FilePreview,
		Mention,
		PlainText,
		Quote,
	},
	props: {
		/**
		 * The sender of the message.
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
		 * The conversation token.
		 */
		token: {
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
		}
	},
	computed: {
		messageTime() {
			return OC.Util.formatDate(this.timestamp * 1000, 'LT')
		},
		quote() {
			return this.parent && this.$store.getters.message(this.token, this.parent)
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

		/**
		 * Messages are parsed in the following way:
		 * 1. We try to find all `{placeholder}`s in the message
		 * 2. Afterwards all parts (parameters and plain text) are added to an array
		 * 3. On rendering we loop over the array and the different blocks are rendered
		 *    by different components.
		 * @returns {Array} the different message parts
		 */
		parsedMessage() {

			const parameters = Object.keys(this.messageParameters)
			const blocks = this.message.split('{')
			const renderBlocks = []

			blocks.forEach((block, index) => {
				if (index === 0) {
					// The first block does not need to get the leading curly brace
					// as it was not split away before.
					renderBlocks.push({
						type: 'plain',
						data: {
							text: block,
						},
					})
					return
				}

				const parts = block.split('}')
				if (parts.length > 1 && parameters.indexOf(parts[0]) !== -1) {
					// Valid parameter
					const placeholder = parts.shift()
					renderBlocks.push({
						type: this.messageParameters[placeholder].type,
						data: this.messageParameters[placeholder],
					})

					if (parts.join('}').length) {
						renderBlocks.push({
							type: 'plain',
							data: {
								text: parts.join('}'),
							},
						})
					}

				// Not a valid parameter - render as plain text
				} else {
					renderBlocks.push({
						type: 'plain',
						data: {
							text: '{' + block,
						},
					})
				}
			})

			return renderBlocks
		},
	},
	methods: {
		getComponentInstanceForMessagePart(messagePartType) {
			if (messagePartType === 'plain') {
				return PlainText
			} else if (messagePartType === 'user') {
				return Mention
			} else if (messagePartType === 'file') {
				return FilePreview
			}
			return DefaultParameter
		},
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
	padding: 4px 0 4px 0;
	flex-direction: column;
	&__author {
		color: var(--color-text-maxcontrast);
	}
	&__main {
		display: flex;
		justify-content: space-between;
		min-width: 100%;
		&__text {
			flex: 1 1 400px;
			color: var(--color-text-light);

			&.single-emoji {
				font-size: 250%;
				line-height: 100%;
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
			flex: 0 0 $message-utils-width;
			display: flex;
			color: var(--color-text-maxcontrast);
			font-size: 13px;
			padding: 0 8px 0 8px;
			&__actions.action-item {
				position: absolute;
				top: -12px;
				right: 0;
			}
		}
	}
}
</style>
