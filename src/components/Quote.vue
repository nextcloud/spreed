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
This component is intended to be used both in `NewMessageForm` and `Message`
components.
</docs>

<template>
	<a href="#"
		class="quote"
		:class="{'quote-own-message': isOwnMessageQuoted}"
		@click.prevent="handleQuoteClick">
		<div class="quote__main">
			<div class="quote__main__author" role="heading" aria-level="4">
				{{ getDisplayName }}
			</div>
			<div v-if="isFileShareMessage"
				class="quote__main__text">
				<RichText :text="message"
					:arguments="richParameters"
					:autolink="true" />
			</div>
			<blockquote v-else
				class="quote__main__text">
				<p>{{ shortenedQuoteMessage }}</p>
			</blockquote>
		</div>
		<div v-if="isNewMessageFormQuote" class="quote__main__right">
			<Actions class="quote__main__right__actions">
				<ActionButton icon="icon-close"
					:close-after-click="true"
					@click.stop="handleAbortReply" />
			</Actions>
		</div>
	</a>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import RichText from '@juliushaertl/vue-richtext'
import FilePreview from './MessagesList/MessagesGroup/Message/MessagePart/FilePreview'
import DefaultParameter from './MessagesList/MessagesGroup/Message/MessagePart/DefaultParameter'
import { EventBus } from '../services/EventBus'

export default {
	name: 'Quote',
	components: {
		Actions,
		ActionButton,
		RichText,
	},
	props: {
		actorId: {
			type: String,
			required: true,
		},
		/**
		 * The sender of the message to be replied to.
		 */
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
		 * The text of the message to be replied to.
		 */
		message: {
			type: String,
			required: true,
		},
		/**
		 * The text of the message to be replied to.
		 */
		messageParameters: {
			type: [Array, Object],
			required: true,
		},
		/**
		 * The message id of the message to be replied to.
		 */
		id: {
			type: Number,
			required: true,
		},
		/**
		 * The conversation token of the message to be replied to.
		 */
		token: {
			type: String,
			required: true,
		},
		/**
		 * If the quote component is used in the `NewMessageForm` component we display
		 * the remove button.
		 */
		isNewMessageFormQuote: {
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
	},
	methods: {
		/**
		 * Stops the quote-reply operation by removing the MessageToBeReplied from
		 * the quoteReplyStore.
		 */
		handleAbortReply() {
			this.$store.dispatch('removeMessageToBeReplied', this.token)
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
		border-left: 4px solid var(--color-primary);
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
