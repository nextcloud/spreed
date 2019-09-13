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
	<div class="message">
		<div class="message-avatar">
			<Avatar v-if="isFirstMessage" :user="userName" :display-name="userName" />
		</div>
		<div class="message-main">
			<div v-if="isFirstMessage" class="message-main-header">
				<h6>{{ userName }}</h6>
			</div>
			<div class="message-main-text">
				<p>{{messageText}}</p>
			</div>
		</div>
		<div class="message-right">
			<h6>{{ messageTime }}</h6>
		</div>
	</div>
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'

export default {
	name: 'Message',
	components: {
		Avatar
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
		}
	}
}
</script>

<style lang="scss" scoped>

.message {
    display: flex;
    width: 100%;
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
		min-width: 90px;
		color: #989898;
		padding: 0px 8px 0 8px;
	}
}

</style>
