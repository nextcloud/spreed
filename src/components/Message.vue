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
	<div
		class="wrapper"
		@mouseover="hover=true"
		@mouseleave="hover=false">
		<div class="message">
			<div class="message-avatar">
				<Avatar v-if="isFirstMessage" :user="userName" :display-name="userName" />
			</div>
			<div class="message-main">
				<div v-if="isFirstMessage" class="message-main-header">
					<h6>{{ userName }}</h6>
				</div>
				<div class="quote">
					<div class="">
					</div>
				</div>
				<div class="message-main-text">
					<p>{{ messageText }}</p>
				</div>
			</div>
			<div class="message-right">
				<h6>{{ messageTime }}</h6>
				<Actions v-if="hover" class="actions">
					<ActionButton icon="icon-edit" @click="alert('Edit')">
						Edit
					</ActionButton>
					<ActionButton icon="icon-delete" @click="alert('Delete')">
						Delete
					</ActionButton>
					<ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
				</Actions>
			</div>
		</div>
		<div />
	</div>
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import Actions from 'nextcloud-vue/dist/Components/Actions'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import ActionLink from 'nextcloud-vue/dist/Components/ActionLink'

export default {
	name: 'Message',
	components: {
		Avatar,
		Actions,
		ActionButton,
		ActionLink
	},
	data: function() {
		return {
			hover: false
		}
	},
	props: {
		userName: {
			type: String,
			required: true
		},
		messageTime: {
			type: String,
			required: true
		},
		messageText: {
			type: String,
			required: true
		},
		isFirstMessage: {
			type: Boolean,
			default: false
		},
		isReply: {
			type: Boolean,
			default: false
		},
		replyQuoteAuthor: {
			type: String,
			default: 'John Doe'
		},
		replyQuoteText: {
			type: String,
			default: 'This is a placeholder reply message'
		}
	}
}
</script>

<style lang="scss" scoped>

.wrapper {
	width: 100%;
	&:hover {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.message {
    display: flex;
    max-width: 600px;
	padding: 12px 0 12px 0;
	margin: auto;
	&-avatar {
		width: 52px;
		min-width: 52px;
		padding: 4px 8px 0 8px;
	}
    &-main {
        display: flex;
		flex-grow: 1;
        flex-direction: column;
		font-size: 20;
		&-header {
			color: #989898;
		}
		&-text {
			color: #000000;
		}
    }
	&-right {
		display: flex;
		min-width: 110px;
		color: #989898;
		padding: 0px 8px 0 8px;
	}

.actions {
	margin-top: -14px;
	padding:2px;
	}
}

</style>
