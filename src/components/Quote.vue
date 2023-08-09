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

<template>
	<a href="#"
		class="quote"
		:class="{'quote-own-message': isOwnMessageQuoted}"
		@click.prevent="handleQuoteClick">
		<div class="quote__main">
			<div v-if="id"
				class="quote__main__author"
				role="heading"
				aria-level="4">
				{{ getDisplayName }}
			</div>
			<div v-if="isFileShareMessage"
				class="quote__main__text">
				<NcRichText :text="message"
					:arguments="richParameters"
					:autolink="true" />
			</div>
			<blockquote v-else
				class="quote__main__text">
				<p>{{ shortenedQuoteMessage }}</p>
			</blockquote>
		</div>
		<div v-if="isNewMessageQuote" class="quote__main__right">
			<NcButton type="tertiary"
				:aria-label="cancelQuoteLabel"
				@click="handleAbortReply">
				<template #icon>
					<Close :size="20" />
				</template>
			</NcButton>
		</div>
	</a>
</template>

<script>
/**
 * This component is intended to be used both in `NewMessage` and `Message`
 * components.
 */

import Close from 'vue-material-design-icons/Close.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'

import DefaultParameter from './MessagesList/MessagesGroup/Message/MessagePart/DefaultParameter.vue'
import FilePreview from './MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'

import { EventBus } from '../services/EventBus.js'

export default {
	name: 'Quote',
	components: {
		NcButton,
		Close,
		NcRichText,
	},
	props: {
		actorId: {
			type: String,
			default: '',
		},
		/**
		 * The sender of the message to be replied to.
		 */
		actorType: {
			type: String,
			default: '',
		},
		/**
		 * The display name of the sender of the message.
		 */
		actorDisplayName: {
			type: String,
			default: '',
		},
		/**
		 * The text of the message to be replied to.
		 */
		message: {
			type: String,
			default: '',
		},
		/**
		 * The text of the message to be replied to.
		 */
		messageParameters: {
			type: [Array, Object],
			default: () => { return {} },
		},
		/**
		 * The message id of the message to be replied to.
		 */
		id: {
			type: Number,
			default: 0,
		},
		/**
		 * The conversation token of the message to be replied to.
		 */
		token: {
			type: String,
			default: '',
		},
		/**
		 * If the quote component is used in the `NewMessage` component we display
		 * the remove button.
		 */
		isNewMessageQuote: {
			type: Boolean,
			default: false,
		},
		/**
		 * The parent message's id
		 */
		parentId: {
			type: Number,
			required: true,
		},
	},
	computed: {
		/**
		 * The message actor display name.
		 *
		 * @return {string}
		 */
		getDisplayName() {
			const displayName = this.actorDisplayName.trim()

			if (displayName === '' && this.actorType === 'guests') {
				return t('spreed', 'Guest')
			}

			if (displayName === '') {
				return t('spreed', 'Deleted user')
			}

			return displayName
		},

		isOwnMessageQuoted() {
			return this.actorId === this.$store.getters.getActorId()
				&& this.actorType === this.$store.getters.getActorType()
		},

		isFileShareMessage() {
			return this.message === '{file}'
				&& 'file' in this.messageParameters
		},

		richParameters() {
			const richParameters = {}
			Object.keys(this.messageParameters).forEach(function(p) {
				const type = this.messageParameters[p].type
				if (type === 'file') {
					richParameters[p] = {
						component: FilePreview,
						props: Object.assign({
							smallPreview: true,
						}, this.messageParameters[p]
						),
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

		/**
		 * This is a simplified version of the last chat message.
		 * Parameters are parsed without markup (just replaced with the name),
		 * e.g. no avatars on mentions.
		 *
		 * @return {string} A simple message to show below the conversation name
		 */
		simpleQuotedMessage() {
			if (!this.id) {
				return t('spreed', 'The message has expired or has been deleted')
			}

			if (!Object.keys(this.messageParameters).length) {
				return this.message
			}

			const params = this.messageParameters
			let subtitle = this.message

			// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
			Object.keys(params).forEach((parameterKey) => {
				subtitle = subtitle.replaceAll('{' + parameterKey + '}', params[parameterKey].name)
			})

			return subtitle
		},
		// Shorten the message to 250 characters and append three dots to the end of the
		// string. This is needed because on very wide screens, if the 250 characters
		// fit, the css rules won't ellipsize the text-overflow.
		shortenedQuoteMessage() {
			if (this.simpleQuotedMessage.length >= 250) {
				return this.simpleQuotedMessage.substring(0, 250) + '…'
			} else {
				return this.simpleQuotedMessage
			}
		},

		cancelQuoteLabel() {
			return t('spreed', 'Cancel quote')
		},
	},
	methods: {
		/**
		 * Stops the quote-reply operation by removing the MessageToBeReplied from
		 * the quoteReplyStore.
		 */
		handleAbortReply() {
			this.$store.dispatch('removeMessageToBeReplied', this.token)
			EventBus.$emit('focus-chat-input')
		},

		handleQuoteClick() {
			EventBus.$emit('focus-message', this.parentId)
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../assets/variables';

.quote {
	border-left: 4px solid var(--color-border-dark);
	margin: 4px 0 4px 8px;
	padding-left: 8px;
	display: flex;
	max-width: $messages-list-max-width - $message-utils-width;

	&.quote-own-message {
		border-left: 4px solid var(--color-primary-element);
	}

	&__main {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		&__author {
			color: var(--color-text-maxcontrast);
		}
		&__text {
			color: var(--color-text-lighter);
			white-space: pre-wrap;
			word-break: break-word;
			& p {
				text-overflow: ellipsis;
				overflow: hidden;
				// Allow 2 lines max and ellipsize the overflow;
				display: -webkit-box;
				-webkit-line-clamp: 2;
				-webkit-box-orient: vertical;
			}
		}
	}
	&__right {
		flex: 0 0 44px;
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		padding: 0 8px 0 8px;
		position: relative;
		margin: auto;
	}
}

</style>
