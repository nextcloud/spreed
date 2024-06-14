<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="wrapper">
		<div class="messages__avatar">
			<AvatarWrapper :id="actorId"
				:token="token"
				:name="actorDisplayName"
				:source="actorType"
				:size="AVATAR.SIZE.SMALL"
				:disable-menu="disableMenu"
				disable-tooltip />
		</div>
		<ul class="messages">
			<li class="messages__author" aria-level="4">
				<span class="messages__author-name">{{ actorDisplayName }}</span>
				<span v-if="isFederatedUser" class="messages__author-server">{{ getRemoteServer }}</span>
				<span v-if="lastEditTimestamp" class="messages__author-edit">{{ getLastEditor }}</span>
			</li>
			<Message v-for="(message, index) of messages"
				:key="message.id"
				:message="message"
				:next-message-id="(messages[index + 1] && messages[index + 1].id) || nextMessageId"
				:previous-message-id="(index > 0 && messages[index - 1].id) || previousMessageId" />
		</ul>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'

import Message from './Message/Message.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'

import { ATTENDEE, AVATAR } from '../../../constants.js'
import { useGuestNameStore } from '../../../stores/guestName.js'

export default {
	name: 'MessagesGroup',

	components: {
		AvatarWrapper,
		Message,
	},

	inheritAttrs: false,

	props: {
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true,
		},
		/**
		 * The messages object.
		 */
		messages: {
			type: Array,
			required: true,
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

	setup() {
		return {
			AVATAR,
			guestNameStore: useGuestNameStore()
		}
	},

	computed: {
		actorId() {
			return this.messages[0].actorId
		},

		actorType() {
			return this.messages[0].actorType
		},

		lastEditTimestamp() {
			return this.messages[0].lastEditTimestamp
		},

		actorDisplayName() {
			const displayName = this.messages[0].actorDisplayName.trim()

			if (this.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				return this.guestNameStore.getGuestName(this.token, this.actorId)
			}

			if (displayName === '') {
				return t('spreed', 'Deleted user')
			}

			return displayName
		},

		isFederatedUser() {
			return this.actorType === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
		},

		getRemoteServer() {
			return this.isFederatedUser ? '(' + this.actorId.split('@').pop() + ')' : ''
		},

		getLastEditor() {
			if (!this.lastEditTimestamp) {
				return ''
			} else if (this.messages[0].lastEditActorId === this.actorId
				&& this.messages[0].lastEditActorType === this.actorType) {
				// TRANSLATORS Edited by the author of the message themselves
				return t('spreed', '(edited)')
			} else if (this.messages[0].lastEditActorId === this.$store.getters.getActorId()
				&& this.messages[0].lastEditActorType === this.$store.getters.getActorType()) {
				return t('spreed', '(edited by you)')
			} else if (this.lastEditActorId === 'deleted_users'
						&& this.lastEditActorType === 'deleted_users') {
				return t('spreed', '(edited by a deleted user)')
			} else {
				return t('spreed', '(edited by {moderator})', { moderator: this.messages[0].lastEditActorDisplayName })
			}
		},

		disableMenu() {
			// disable the menu if accessing the conversation as guest
			// or the message sender is a bridged user
			return this.$store.getters.isActorGuest() || this.actorType === ATTENDEE.ACTOR_TYPE.BRIDGED
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables';

.wrapper {
	max-width: $messages-list-max-width;
	display: flex;
	margin: auto;
	padding: 0;

	&:focus {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.messages {
	flex: auto;
	display: flex;
	padding: 8px 0 8px 0;
	flex-direction: column;
	width: 100%;
	min-width: 0;

	&__avatar {
		position: sticky;
		top: 0;
		height: 52px;
		width: 52px;
		padding: 18px 10px 10px 10px;
	}

	&__author {
		display: flex;
		gap: 4px;
		max-width: $messages-text-max-width;
		padding: var(--default-grid-baseline) 0 0 calc(2 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);

		&-name {
			flex-shrink: 0;
		}

		&-edit,
		&-server {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}
</style>
