<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<docs>
This component is intended to be used both in `NewMessage` and `Message`
components.
</docs>

<template>
	<component :is="component.tag"
		:to="component.link"
		class="quote"
		:class="{ 'quote-own-message': isOwnMessageQuoted }"
		@click="handleQuoteClick">
		<div class="quote__main">
			<div v-if="message.id"
				class="quote__main__author"
				role="heading"
				aria-level="4">
				<AvatarWrapper :id="message.actorId"
					:token="message.token"
					:name="actorDisplayName"
					:source="message.actorType"
					:size="AVATAR.SIZE.EXTRA_SMALL"
					disable-menu />
				<span class="quote__main__author-info">{{ actorInfo }}</span>
				<div v-if="editMessage" class="quote__main__edit-hint">
					<PencilIcon :size="20" />
					{{ t('spreed', '(editing)') }}
				</div>
			</div>
			<!-- File preview -->
			<NcRichText v-if="isFileShare"
				text="{file}"
				:arguments="richParameters" />
			<!-- Message text -->
			<blockquote v-if="!isFileShareWithoutCaption" dir="auto" class="quote__main__text">
				{{ shortenedQuoteMessage }}
			</blockquote>
		</div>

		<NcButton v-if="canCancel"
			class="quote__close"
			variant="tertiary"
			:title="cancelQuoteLabel"
			:aria-label="cancelQuoteLabel"
			@click="handleAbort">
			<template #icon>
				<Close :size="20" />
			</template>
		</NcButton>
	</component>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { computed, toRefs } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import Close from 'vue-material-design-icons/Close.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import AvatarWrapper from './AvatarWrapper/AvatarWrapper.vue'
import DefaultParameter from './MessagesList/MessagesGroup/Message/MessagePart/DefaultParameter.vue'
import FilePreview from './MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import { useMessageInfo } from '../composables/useMessageInfo.js'
import { ATTENDEE, AVATAR } from '../constants.ts'
import { EventBus } from '../services/EventBus.ts'
import { useActorStore } from '../stores/actor.ts'
import { useChatExtrasStore } from '../stores/chatExtras.js'

export default {
	name: 'Quote',

	components: {
		AvatarWrapper,
		NcButton,
		NcRichText,
		// Icons
		Close,
		PencilIcon,
	},

	props: {
		// The quoted message object
		message: {
			type: Object,
			required: true,
		},

		// Whether to show remove / cancel action
		canCancel: {
			type: Boolean,
			default: false,
		},

		// Whether to show edit actions
		editMessage: {
			type: Boolean,
			default: false,
		},
	},

	setup(props) {
		const { message } = toRefs(props)
		const chatExtrasStore = useChatExtrasStore()
		const {
			isFileShare,
			isFileShareWithoutCaption,
			remoteServer,
			lastEditor,
			actorDisplayName,
			actorDisplayNameWithFallback,
		} = useMessageInfo(message)

		const actorInfo = computed(() => {
			return [actorDisplayNameWithFallback.value, remoteServer.value, lastEditor.value]
				.filter((value) => value).join(' ')
		})

		return {
			AVATAR,
			chatExtrasStore,
			isFileShare,
			isFileShareWithoutCaption,
			actorDisplayName,
			actorInfo,
			actorStore: useActorStore(),
		}
	},

	computed: {
		component() {
			return this.canCancel
				? { tag: 'div', link: undefined }
				: { tag: 'router-link', link: { hash: this.hash } }
		},

		isOwnMessageQuoted() {
			return this.actorStore.checkIfSelfIsActor(this.message)
		},

		richParameters() {
			const richParameters = {}
			Object.keys(this.message.messageParameters).forEach(function(p) {
				const type = this.message.messageParameters[p].type
				if (type === 'file') {
					richParameters[p] = {
						component: FilePreview,
						props: {
							token: this.message.token,
							smallPreview: true,
							rowLayout: !this.message.messageParameters[p].mimetype.startsWith('image/'),
							file: this.message.messageParameters[p],
						},
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

		/**
		 * This is a simplified version of the last chat message.
		 * Parameters are parsed without markup (just replaced with the name),
		 * e.g. no avatars on mentions.
		 *
		 * @return {string} A simple message to show below the conversation name
		 */
		simpleQuotedMessage() {
			if (!this.message.id) {
				return t('spreed', 'The message has expired or has been deleted')
			}

			if (!Object.keys(this.message.messageParameters).length) {
				return this.message.message
			}

			const params = this.message.messageParameters
			let subtitle = this.message.message

			// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
			Object.keys(params).forEach((parameterKey) => {
				subtitle = subtitle.replaceAll('{' + parameterKey + '}', params[parameterKey].name)
			})

			return subtitle
		},

		/**
		 * Shorten the message to 250 characters and append three dots to the end of the
		 * string. This is needed because on very wide screens, if the 250 characters
		 * fit, the css rules won't ellipsize the text-overflow.
		 */
		shortenedQuoteMessage() {
			return this.simpleQuotedMessage.length >= 250
				? this.simpleQuotedMessage.substring(0, 250) + '…'
				: this.simpleQuotedMessage
		},

		cancelQuoteLabel() {
			return t('spreed', 'Cancel quote')
		},

		hash() {
			return '#message_' + this.message.id
		},
	},

	methods: {
		t,
		handleAbort() {
			if (this.editMessage) {
				this.chatExtrasStore.removeMessageIdToEdit(this.message.token)
			} else {
				this.chatExtrasStore.removeParentIdToReply(this.message.token)
			}
			EventBus.emit('focus-chat-input')
		},

		handleQuoteClick() {
			if (this.canCancel) {
				return
			}

			if (this.$route.hash === this.hash) {
				// Already on this message route, just trigger highlight
				EventBus.emit('focus-message', this.message.id)
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
	padding-block: 6px;
	padding-inline-end: 6px;
	padding-inline-start: 24px;
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
		inset-inline-start: 8px;
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
		width: 100%;

		&__author {
			display: flex;
			align-items: center;
			gap: 4px;
			color: var(--color-text-maxcontrast);

			&-info {
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}

		&__text {
			color: var(--color-text-maxcontrast);
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
			text-align: start;
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
		inset-inline-end: 4px;
	}
}

</style>
