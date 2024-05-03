<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<docs>
This component is intended to be used both in `NewMessage` and `Message`
components.
</docs>

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
				<AvatarWrapper :id="actorId"
					:token="token"
					:name="getDisplayName"
					:source="actorType"
					:size="AVATAR.SIZE.EXTRA_SMALL"
					disable-menu />
				{{ getDisplayName }}
				<div v-if="editMessage" class="quote__main__edit-hint">
					<PencilIcon :size="20" />
					{{ t('spreed', '(editing)') }}
				</div>
			</div>
			<!-- file preview-->
			<NcRichText v-if="isFileShare"
				text="{file}"
				:arguments="richParameters" />
			<!-- text -->
			<blockquote v-if="!isFileShareWithoutCaption"
				class="quote__main__text">
				<p dir="auto">{{ shortenedQuoteMessage }}</p>
			</blockquote>
		</div>

		<NcButton v-if="canCancel"
			class="quote__close"
			type="tertiary"
			:aria-label="cancelQuoteLabel"
			@click="handleAbort">
			<template #icon>
				<Close :size="20" />
			</template>
		</NcButton>
	</a>
</template>

<script>
import { toRefs } from 'vue'

import Close from 'vue-material-design-icons/Close.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'

import AvatarWrapper from './AvatarWrapper/AvatarWrapper.vue'
import DefaultParameter from './MessagesList/MessagesGroup/Message/MessagePart/DefaultParameter.vue'
import FilePreview from './MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'

import { useMessageInfo } from '../composables/useMessageInfo.js'
import { ATTENDEE, AVATAR } from '../constants.js'
import { EventBus } from '../services/EventBus.js'
import { useChatExtrasStore } from '../stores/chatExtras.js'

export default {
	name: 'Quote',
	components: {
		AvatarWrapper,
		NcButton,
		Close,
		NcRichText,
		PencilIcon,
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
		canCancel: {
			type: Boolean,
			default: false,
		},

		editMessage: {
			type: Boolean,
			default: false,
		},
	},

	setup(props) {
		const { token, id } = toRefs(props)
		const chatExtrasStore = useChatExtrasStore()
		const {
			isFileShare,
			isFileShareWithoutCaption,
		} = useMessageInfo(token, id)

		return {
			AVATAR,
			chatExtrasStore,
			isFileShare,
			isFileShareWithoutCaption,
		}
	},

	computed: {
		/**
		 * The message actor display name.
		 *
		 * @return {string}
		 */
		getDisplayName() {
			const displayName = this.actorDisplayName.trim()

			if (displayName === '' && this.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
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

		richParameters() {
			const richParameters = {}
			Object.keys(this.messageParameters).forEach(function(p) {
				const type = this.messageParameters[p].type
				if (type === 'file') {
					richParameters[p] = {
						component: FilePreview,
						props: Object.assign({
							token: this.token,
							smallPreview: true,
							rowLayout: !this.messageParameters[p].mimetype.startsWith('image/'),
						}, this.messageParameters[p]),
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
		handleAbort() {
			if (this.editMessage) {
				this.chatExtrasStore.removeMessageIdToEdit(this.token)
			} else {
				this.chatExtrasStore.removeParentIdToReply(this.token)
			}
			EventBus.emit('focus-chat-input')
		},

		handleQuoteClick() {
			const parentHash = '#message_' + this.id
			if (this.$route.hash !== parentHash) {
				// Change route to trigger message fetch, if not fetched yet
				this.$router.replace(parentHash)
			} else {
				// Already on this message route, just trigger highlight
				EventBus.emit('focus-message', this.id)
			}
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../assets/variables';

.quote {
	position: relative;
	margin: 4px 0;
	padding: 6px 6px 6px 24px;
	display: flex;
	max-width: $messages-text-max-width;
	border-radius: var(--border-radius-large);
	border: 2px solid var(--color-border);
	background-color: var(--color-main-background);
	overflow: hidden;

	&::before {
		content: ' ';
		position: absolute;
		top: 8px;
		left: 8px;
		height: calc(100% - 16px);
		width: 8px;
		border-radius: var(--border-radius);
		background-color: var(--color-border);
	}

	&.quote-own-message::before {
		background-color: var(--color-primary-element);
	}

	&__main {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		&__author {
			display: flex;
			align-items: center;
			gap: 4px;
			color: var(--color-text-maxcontrast);
		}
		&__text {
			color: var(--color-text-maxcontrast);
			white-space: pre-wrap;
			word-break: break-word;
			& p {
				text-overflow: ellipsis;
				overflow: hidden;
				// Allow 1 line max and ellipsize the overflow;
				display: -webkit-box;
				-webkit-line-clamp: 1;
				-webkit-box-orient: vertical;
				text-align: start;
			}
		}
		&__edit-hint {
			display: flex;
			align-items: center;
			gap: 4px;
		}
	}

	&__close {
		position: absolute !important;
		top: 4px;
		right: 4px;
	}
}

</style>
