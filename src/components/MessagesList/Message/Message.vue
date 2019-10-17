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

<template>
	<div class="wrapper">
		<div class="messages__avatar">
			<Avatar
				class="messages__avatar__icon"
				:user="actorDisplayName"
				:display-name="actorDisplayName" />
		</div>
		<div class="messages">
			<MessageBody
				v-for="message of messages"
				v-bind="message"
				:key="message.id"
				:hover="hover"
				:actor-display-name="actorDisplayName"
				:isTemporary="isTemporary" />
		</div>
	</div>
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'

import MessageBody from './MessageBody/MessageBody'

export default {
	name: 'Message',
	components: {
		Avatar,
		MessageBody
	},
	props: {
		/**
		 * The message id.
		 */
		id: {
			type: Number,
			required: true
		},
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true
		},
		/**
		 * The messages object.
		 */
		messages: {
			type: Array,
			required: true
		}
	},

	computed: {
		/**
		 * The message username.
		 * @returns {string}
		 */
		actorDisplayName() {
			return this.messages[0].actorDisplayName
		},
		isTemporary() {
			return this.messages[0].timestamp === 0
		}
	}
}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables';

.wrapper {
	max-width: $message-width;
	display: flex;
	margin: auto;
	padding: 0 0 0 0;
	&:focus {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.messages {
	display: flex;
	padding: 8px 0 8px 0;
	flex-direction: column;
	&__avatar {
		min-height: 100%;
		width: 52px;
		min-width: 52px;
		padding: 4px 8px 0 8px;
		&__icon {
			position: sticky;
			top: 16px;
		}
	}
}
</style>
