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
				{{ actorInfo }}
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
import { computed, toRefs } from 'vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import Message from './Message/Message.vue'
import { useMessageInfo } from '../../../composables/useMessageInfo.js'
import { ATTENDEE, AVATAR } from '../../../constants.ts'
import { useActorStore } from '../../../stores/actor.ts'
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

	setup(props) {
		const { messages } = toRefs(props)
		const firstMessage = computed(() => messages.value[0])
		const {
			remoteServer,
			lastEditor,
			actorDisplayName,
			actorDisplayNameWithFallback,
		} = useMessageInfo(firstMessage)

		const actorInfo = computed(() => {
			return [actorDisplayNameWithFallback.value, remoteServer.value, lastEditor.value]
				.filter((value) => value).join(' ')
		})

		return {
			AVATAR,
			guestNameStore: useGuestNameStore(),
			actorStore: useActorStore(),
			actorDisplayName,
			actorInfo,
		}
	},

	computed: {
		actorId() {
			return this.messages[0].actorId
		},

		actorType() {
			return this.messages[0].actorType
		},

		disableMenu() {
			// disable the menu if accessing the conversation as guest
			// or the message sender is a bridged user
			return this.actorStore.isActorGuest || this.actorType === ATTENDEE.ACTOR_TYPE.BRIDGED
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
	display: flex;
	align-items: flex-start;
	width: 100%;
	padding: 0;

	&:focus {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.messages {
	flex: auto;
	display: flex;
	padding: var(--default-grid-baseline) 0;
	flex-direction: column;
	width: 100%;
	min-width: 0;

	&__avatar {
		position: sticky;
		top: 0;
		padding: calc(2 * var(--default-grid-baseline));
		margin-top: calc(2 * var(--default-grid-baseline));
	}

	&__author {
		max-width: $messages-text-max-width;
		padding-inline-start: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
		flex-shrink: 0;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}
</style>
