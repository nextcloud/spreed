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
	<div class="quote">
		<div class="quote__main">
			<div class="quote__main__author">
				<h6>{{ actorDisplayName }}</h6>
			</div>
			<div class="quote__main__text">
				<p>{{ shortenQuotedMessage }}</p>
			</div>
		</div>
		<div v-if="isNewMessageFormQuote" class="quote__main__right">
			<Actions class="quote__main__right__actions">
				<ActionButton
					icon="icon-close"
					:close-after-click="true"
					@click.stop="handleAbortReply" />
			</Actions>
		</div>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'

export default {
	name: 'Quote',
	components: {
		Actions,
		ActionButton,
	},
	props: {
		/**
		 * The sender of the message to be replied to.
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
	},
	computed: {
		/**
		 * This is a simplified version of the last chat message.
		 * Parameters are parsed without markup (just replaced with the name),
		 * e.g. no avatars on mentions.
		 * @returns {string} A simple message to show below the conversation name
		 */
		simpleQuotedMessage() {
			if (!Object.keys(this.messageParameters).length) {
				return this.message
			}

			const params = this.messageParameters
			let subtitle = this.message

			// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
			Object.keys(params).forEach((parameterKey) => {
				subtitle = subtitle.replace('{' + parameterKey + '}', params[parameterKey].name)
			})

			return subtitle
		},

		shortenQuotedMessage() {
			let cutAtChar = 200

			// We allow a maximum of 2 line breaks
			const firstNewline = this.simpleQuotedMessage.indexOf('\n')
			if (firstNewline !== -1) {
				const secondNewline = this.simpleQuotedMessage.indexOf('\n', firstNewline + 1)
				if (secondNewline !== -1) {
					cutAtChar = Math.min(cutAtChar, secondNewline)
				}
			}

			let cuttingAfterWord = true
			if (cutAtChar <= this.simpleQuotedMessage.length) {
				// When we cut the string, we look for the next space.
				const nextSpace = this.simpleQuotedMessage.indexOf(' ', cutAtChar)
				if (nextSpace !== -1 && nextSpace < cutAtChar + 15) {
					// If it is in a reasonable distance, we finish the word and cut after it
					cutAtChar = nextSpace
				} else {
					// If not, we look if there is a space reasonable before the cut position
					const previousSpace = this.simpleQuotedMessage.lastIndexOf(' ', cutAtChar)
					if (previousSpace !== -1 && previousSpace > cutAtChar - 15) {
						cutAtChar = previousSpace
					} else {
						// We didn't find any space in near distance, so we cut
						// just where we are and add the … at the position,
						// since we cut in the middle of the word.
						cuttingAfterWord = false
					}
				}
			} else {
				return this.simpleQuotedMessage
			}

			let message = this.simpleQuotedMessage.substr(0, cutAtChar)
			if (cuttingAfterWord) {
				message += ' …'
			} else {
				message += '…'
			}
			return message
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
	},
}
</script>

<style lang="scss" scoped>

@import '../assets/variables';

.quote {
	border-left: 4px solid var(--color-primary);
	margin: 10px 0 10px 0;
	padding: 0 0 0 10px;
	display: flex;
	max-width: $messages-list-max-width;
	margin: 0 $message-utils-width 0 0;
	&__main {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		&__author {
			color: var(--color-text-maxcontrast);
		}
		&__text {
			color: var(--color-text-light);
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
