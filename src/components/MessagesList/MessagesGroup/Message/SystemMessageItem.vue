<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		:id="`message_${message.id}`"
		:data-message-id="message.id"
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
				:richParameters="richParameters"
				:hasCall="conversation.hasCall"
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
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconUnfoldLessHorizontal from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import IconUnfoldMoreHorizontal from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
import DefaultParameter from './MessagePart/DefaultParameter.vue'
import MentionChip from './MessagePart/MentionChip.vue'
import MessageBody from './MessagePart/MessageBody.vue'
import { MENTION } from '../../../../constants.ts'

export default {
	name: 'MessageItem',

	components: {
		IconUnfoldLessHorizontal,
		IconUnfoldMoreHorizontal,
		MessageBody,
		NcButton,
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

	computed: {
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

		toggleCombinedSystemMessage() {
			this.$emit('toggleCombinedSystemMessage')
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

.message-buttons-bar {
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
	box-shadow: 0 0 4px 0 var(--color-box-shadow);
	height: var(--default-clickable-area);
	z-index: 1;
	opacity: 0;
}
</style>
