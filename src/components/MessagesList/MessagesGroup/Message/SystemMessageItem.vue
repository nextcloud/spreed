<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		:id="`message_${message.id}`"
		:data-message-id="message.id"
		:data-seen="seen"
		:data-next-message-id="nextMessageId"
		:data-previous-message-id="previousMessageId"
		class="message">
		<div
			:class="{
				'combined-system': isCombinedSystemMessage,
				'combined-system--open': isCombinedSystemMessage && !isCombinedSystemMessageCollapsed,
			}"
			class="message-body system">
			<MessageBody
				:rich-parameters="richParameters"
				:has-call="conversation.hasCall"
				:message="message" />
		</div>

		<!-- Message actions -->
		<div class="message-body__scroll">
			<div
				v-if="isCombinedSystemMessage"
				class="message-buttons-bar">
				<NcButton
					variant="tertiary"
					:aria-label="t('spreed', 'Show or collapse system messages')"
					:title="t('spreed', 'Show or collapse system messages')"
					@click="toggleCombinedSystemMessage">
					<template #icon>
						<IconUnfoldMoreHorizontal v-if="isCombinedSystemMessageCollapsed" />
						<IconUnfoldLessHorizontal v-else />
					</template>
				</NcButton>
			</div>
		</div>

		<div
			v-if="isLastReadMessage"
			v-intersection-observer="lastReadMessageVisibilityChanged"
			class="message-unread-marker">
			<div class="message-unread-marker__wrapper">
				<span class="message-unread-marker__text">{{ t('spreed', 'Unread messages') }}</span>
				<NcAssistantButton
					v-if="shouldShowSummaryOption"
					:disabled="loading"
					@click="generateSummary">
					{{ t('spreed', 'Generate summary') }}
				</NcAssistantButton>
			</div>
		</div>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { vIntersectionObserver as IntersectionObserver } from '@vueuse/components'
import NcAssistantButton from '@nextcloud/vue/components/NcAssistantButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconUnfoldLessHorizontal from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import IconUnfoldMoreHorizontal from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import MentionChip from './MessagePart/MentionChip.vue'
import MessageBody from './MessagePart/MessageBody.vue'
import { MENTION } from '../../../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../../../services/CapabilitiesManager.ts'
import { useChatExtrasStore } from '../../../../stores/chatExtras.ts'

const canSummarizeChat = hasTalkFeature('local', 'chat-summary-api')
const summaryThreshold = getTalkConfig('local', 'chat', 'summary-threshold') ?? 0

export default {
	name: 'MessageItem',

	components: {
		IconUnfoldLessHorizontal,
		IconUnfoldMoreHorizontal,
		MessageBody,
		NcAssistantButton,
		NcButton,
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

	setup() {
		return {
			chatExtrasStore: useChatExtrasStore(),
		}
	},

	data() {
		return {
			loading: false,
			// whether the message was seen, only used if this was marked as last read message
			seen: false,
		}
	},

	computed: {
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

		conversation() {
			return this.$store.getters.conversation(this.message.token)
		},

		richParameters() {
			const richParameters = {}
			Object.keys(this.message.messageParameters).forEach(function(p) {
				const type = this.message.messageParameters[p].type
				if (Object.values(MENTION.TYPE).includes(type)) {
					richParameters[p] = {
						component: MentionChip,
						props: {
							...this.message.messageParameters[p],
							token: this.message.token,
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
	},

	methods: {
		t,
		lastReadMessageVisibilityChanged([{ isIntersecting }]) {
			if (isIntersecting) {
				this.seen = true
			}
		},

		toggleCombinedSystemMessage() {
			this.$emit('toggleCombinedSystemMessage')
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

	&:hover .combined-system,
	&:focus-within .combined-system {
		border-radius: 8px;
		background-color: var(--color-background-hover);
	}
	&:hover .message-buttons-bar,
	&:focus-within .message-buttons-bar,
	.combined-system--open + .message-body__scroll > .message-buttons-bar {
		opacity: 1;
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
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
	box-shadow: 0 0 4px 0 var(--color-box-shadow);
	height: var(--default-clickable-area);
	z-index: 1;
	opacity: 0;
}
</style>
