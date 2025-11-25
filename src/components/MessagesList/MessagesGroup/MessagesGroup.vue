<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="wrapper" :class="{ outgoing: isSelfActor && isSplitViewEnabled, incoming: !isSelfActor && isSplitViewEnabled }" tabindex="-1">
		<div
			v-if="!isSplitViewEnabled || !isSelfActor || !showAuthor"
			class="messages__avatar-wrapper">
			<AvatarWrapper
				:id="actorId"
				class="messages__avatar"
				:token="token"
				:name="actorDisplayName"
				:source="actorType"
				:size="AVATAR.SIZE.SMALL"
				:disable-menu="disableMenu"
				disable-tooltip />
		</div>
		<div class="messages__content" :class="{ 'small-view': isMobile || isSidebar }">
			<li v-if="showAuthor" class="messages__author" aria-level="4">
				{{ actorInfo }}
			</li>
			<ul class="messages" :class="{ 'messages-bubble': isSplitViewEnabled }">
				<MessageItem
					v-for="(message, index) of messages"
					:key="message.id"
					:class="{
						incoming: !isSelfActor && isSplitViewEnabled,
						outgoing: isSelfActor && isSplitViewEnabled,
					}"
					:message="message"
					:next-message-id="(messages[index + 1] && messages[index + 1].id) || nextMessageId"
					:previous-message-id="(index > 0 && messages[index - 1].id) || previousMessageId"
					:is-self-actor />
			</ul>
		</div>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, inject, toRefs } from 'vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import MessageItem from './Message/MessageItem.vue'
import { useMessageInfo } from '../../../composables/useMessageInfo.ts'
import { ATTENDEE, AVATAR, CHAT } from '../../../constants.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useGuestNameStore } from '../../../stores/guestName.ts'

export default {
	name: 'MessagesGroup',

	components: {
		AvatarWrapper,
		MessageItem,
	},

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
		const isSidebar = inject('chatView:isSidebar', false)

		const actorInfo = computed(() => {
			return [actorDisplayNameWithFallback.value, remoteServer.value, lastEditor.value]
				.filter((value) => value).join(' ')
		})

		const isSplitViewEnabled = inject('messagesList:isSplitViewEnabled', true)

		return {
			AVATAR,
			guestNameStore: useGuestNameStore(),
			actorStore: useActorStore(),
			actorDisplayName,
			actorInfo,
			isMobile: useIsMobile(),
			isSidebar,
			isSplitViewEnabled,
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

		isSelfActor() {
			return this.actorStore.checkIfSelfIsActor({ actorId: this.actorId, actorType: this.actorType })
		},

		showAuthor() {
			return !this.isSplitViewEnabled || !this.isSelfActor || this.isMobile || this.isSidebar
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
@use '../../../assets/variables' as *;

.wrapper {
	position: relative;
	width: 100%;
	padding: var(--default-grid-baseline) 0;

	&:focus:not(.outgoing):not(.incoming) {
		background-color: rgba(47, 47, 47, 0.068);
	}

	// BEGIN Split view: own messages on the right side
	&.outgoing {

		.messages__author {
			text-align: end;
			padding-inline-end: var(--default-grid-baseline);
		}

		.messages__content {
			padding-inline-end: $messages-avatar-width;
			padding-inline-start: 0;
			display: flex;
			justify-content: flex-end;

			&.small-view {
				display: block;
				padding-inline-end: var(--default-grid-baseline);
				padding-inline-start: $messages-avatar-width;
			}
		}

		.messages__avatar-wrapper {
			inset-inline-start: unset;
			inset-inline-end: 0;
			padding-block-start: 0;
		}
	}

	&.incoming {
		.messages__avatar-wrapper {
			padding-block-start: 0;
		}
	}
	// END Split view: own messages on the right side
}

.messages {
	padding-block: var(--default-grid-baseline);
	width: 100%;

	&__avatar-wrapper {
		position: absolute;
		top: 0;
		inset-inline-start: 0;
		height: 100%;
		padding-block: calc(3 * var(--default-grid-baseline));
		padding-inline: calc(2 * var(--default-grid-baseline));
	}

	&__avatar {
		position: sticky;
		top: 0;
	}

	&__content {
		padding-inline-start: $messages-avatar-width;
	}

	&__author {
		padding-inline-start: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	// BEGIN Split view
	&-bubble {
		gap: 2px;
		display: flex;
		flex-direction: column;
	}

	.outgoing &-bubble {

		// all children except the last one are edged on the end side
		& > li:not(:last-child) :deep(.message-body) {
			border-end-end-radius: var(--border-radius-small);
		}
	}

	.incoming &-bubble {

		// all children except the last one are edged on the start side
		& > li:not(:last-child) :deep(.message-body) {
			border-end-start-radius: var(--border-radius-small);
		}

	}
	// END Split view
}
</style>
