<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="empty-call-view"
		:class="{
			'empty-call-view--sidebar': isSidebar,
			'empty-call-view--small': isSmall
		}"
		data-theme-dark>
		<component :is="emptyCallViewIcon" :size="isSidebar ? 32 : 64" class="empty-call-view__icon" />
		<h2>{{ title }}</h2>
		<template v-if="!isSmall">
			<p v-if="message" class="emptycontent-additional">
				{{ message }}
			</p>
			<NcButton v-if="showLink"
				type="primary"
				@click.stop="handleCopyLink">
				{{ t('spreed', 'Copy link') }}
			</NcButton>
		</template>
	</div>
</template>

<script>
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconLink from 'vue-material-design-icons/Link.vue'
import IconPhone from 'vue-material-design-icons/Phone.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
import { copyConversationLinkToClipboard } from '../../../utils/handleUrl.ts'

export default {

	name: 'EmptyCallView',

	components: {
		NcButton,
		NcLoadingIcon,
		IconAccountMultiple,
		IconLink,
		IconPhone,
	},

	props: {
		isGrid: {
			type: Boolean,
			default: false,
		},

		isSidebar: {
			type: Boolean,
			default: false,
		},

		isSmall: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		isConnecting() {
			return this.$store.getters.isConnecting(this.token)
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		isGroupConversation() {
			return this.conversation && this.conversation.type === CONVERSATION.TYPE.GROUP
		},

		isPublicConversation() {
			return this.conversation && this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},

		isOneToOneConversation() {
			return this.conversation?.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation?.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		isPasswordRequestConversation() {
			return this.conversation && this.conversation.objectType === CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION
		},

		isFileConversation() {
			return this.conversation && this.conversation.objectType === CONVERSATION.OBJECT_TYPE.FILE
		},

		isPhoneConversation() {
			return this.conversation && this.conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE
		},

		conversationDisplayName() {
			return this.conversation && this.conversation.displayName
		},

		canInviteOthers() {
			return this.conversation && (
				this.conversation.participantType === PARTICIPANT.TYPE.OWNER
				|| this.conversation.participantType === PARTICIPANT.TYPE.MODERATOR)
		},

		canInviteOthersInPublicConversations() {
			return this.canInviteOthers
				|| (this.conversation && this.conversation.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		emptyCallViewIcon() {
			if (this.isConnecting) {
				return NcLoadingIcon
			} else if (this.isPhoneConversation) {
				return IconPhone
			} else {
				return this.isPublicConversation ? IconLink : IconAccountMultiple
			}
		},

		title() {
			if (this.isConnecting) {
				return t('spreed', 'Connecting …')
			}
			if (this.isPhoneConversation) {
				return t('spreed', 'Calling …')
			}
			if (this.isOneToOneConversation) {
				return t('spreed', 'Waiting for {user} to join the call', { user: this.conversationDisplayName })
			}
			return t('spreed', 'Waiting for others to join the call …')
		},

		message() {
			if (this.isConnecting) {
				return ''
			}

			if (this.isPasswordRequestConversation || this.isFileConversation) {
				return ''
			}

			if (!this.isGroupConversation && !this.isPublicConversation) {
				return ''
			}

			if (this.isGroupConversation && !this.canInviteOthers) {
				return ''
			}

			if (this.isPhoneConversation) {
				return ''
			}

			if (this.isGroupConversation) {
				return t('spreed', 'You can invite others in the participant tab of the sidebar')
			}

			if (this.isPublicConversation && this.canInviteOthersInPublicConversations) {
				return t('spreed', 'You can invite others in the participant tab of the sidebar or share this link to invite others!')
			}

			return t('spreed', 'Share this link to invite others!')
		},

		showLink() {
			return this.isPublicConversation && !this.isPasswordRequestConversation && !this.isFileConversation
		},
	},

	methods: {
		t,
		handleCopyLink() {
			copyConversationLinkToClipboard(this.token)
		},
	},
}
</script>

<style lang="scss" scoped>

.empty-call-view {
	height: 100%;
	width: 100%;
	position: absolute;
	display: flex;
	flex-direction: column;
	align-content: center;
	justify-content: center;
	text-align: center;
	z-index: 1; // Otherwise the "Copy link" button is not clickable

	.icon {
		background-size: 64px;
		height: 64px;
		width: 64px;
		margin: 0 auto 15px;
	}

	button {
		margin: 4px auto;
	}

	&__icon,
	h2, p {
		color: var(--color-main-text);
	}

	&--sidebar {
		padding-bottom: 16px;

		h2, p {
			font-size: 90%;
		}

		.icon {
			transform: scale(0.7);
			margin-top: 0;
			margin-bottom: 0;
		}
	}

	&--small {
		border-radius: calc(var(--default-clickable-area) / 2);
		background-color: rgba(34, 34, 34, 0.8); /* Copy from the call view */
		padding: 8px;

		h2 {
			font-size: 1rem;
			font-weight: normal;
		}

		.icon {
			transform: none;
			margin-bottom: 0;
			background-size: 32px;
			height: 32px;
			width: 32px;
		}
	}
}

</style>
