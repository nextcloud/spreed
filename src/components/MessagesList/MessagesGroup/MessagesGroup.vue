<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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

<template>
	<div class="wrapper">
		<div class="messages__avatar">
			<AuthorAvatar :author-type="actorType"
				:author-id="actorId"
				:display-name="actorDisplayName" />
		</div>
		<ul class="messages">
			<li class="messages__author" aria-level="4">
				{{ actorDisplayName }}
			</li>
			<Message v-for="(message, index) of messages"
				:key="message.id"
				ref="message"
				v-bind="message"
				:is-temporary="message.timestamp === 0"
				:next-message-id="(messages[index + 1] && messages[index + 1].id) || nextMessageId"
				:previous-message-id="(index > 0 && messages[index - 1].id) || previousMessageId"
				:actor-type="actorType"
				:actor-id="actorId" />
		</ul>
	</div>
</template>

<script>
import AuthorAvatar from './AuthorAvatar.vue'
import Message from './Message/Message.vue'

import { ATTENDEE } from '../../../constants.js'
import { useGuestNameStore } from '../../../store/guestNameStore.js'

export default {
	name: 'MessagesGroup',

	components: {
		AuthorAvatar,
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
		const guestNameStore = useGuestNameStore()
		return  guestNameStore
	},

	expose: ['highlightMessage'],

	computed: {
		/**
		 * The message actor type.
		 *
		 * @return {string}
		 */
		actorType() {
			return this.messages[0].actorType
		},
		/**
		 * The message actor id.
		 *
		 * @return {string}
		 */
		actorId() {
			return this.messages[0].actorId
		},
		/**
		 * The message actor display name.
		 *
		 * @return {string}
		 */
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
	},

	methods: {
		highlightMessage(messageId) {
			for (const message of this.$refs.message) {
				if (message.id === messageId) {
					message.highlightMessage()
					break
				}
			}
		},
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
		padding: 4px 0 0 8px;
		color: var(--color-text-maxcontrast);
	}
}
</style>
