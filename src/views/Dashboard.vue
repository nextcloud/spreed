<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
  -
  -->

<template>
	<ul v-if="roomOptions.length > 0">
		<li v-for="conversation in roomOptions" :key="conversation.token">
			<a :href="callLink(conversation)" class="conversation">
				<ConversationIcon
					:item="conversation"
					:hide-favorite="false"
					:hide-call="false" />
				<div class="conversation__details">
					<h3>{{ conversation.displayName }}</h3>
					<p class="message">{{ conversation.lastMessage.message }}</p>
				</div>
				<button v-if="conversation.hasCall" class="primary success">{{ t('spreed', 'Join call') }}</button>
			</a>
		</li>
	</ul>
	<div v-else>
		<EmptyContent icon="icon-talk">
			{{ t('spreed', 'Join a conversation or start a new one') }}
			<template #desc>
				<p>{{ t('spreed', 'Say hi to your friends and colleagues!') }}</p>
				<button>{{ t('spreed', 'Start a conversation') }}</button>
			</template>
		</EmptyContent>
	</div>
</template>

<script>
import ConversationIcon from './../components/ConversationIcon'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'Dashboard',
	components: { ConversationIcon, EmptyContent },
	data() {
		return {
			roomOptions: [],
		}
	},
	computed: {
		callLink() {
			return (conversation) => {
				return '/index.php/call/' + conversation.token
			}
		},
	},
	beforeMount() {
		this.fetchRooms()
		setInterval(() => this.fetchRooms(), 5000)
	},
	methods: {
		fetchRooms() {
			axios.get(generateOcsUrl('/apps/spreed/api/v1', 2) + 'room').then((response) => {
				const rooms = response.data.ocs.data.slice(0, 6)
				rooms.sort((a, b) => b.lastActivity - a.lastActivity)
				this.roomOptions = rooms
			})
		},
	},
}
</script>

<style lang="scss" scoped>
	li a {
		display: flex;
		align-items: flex-start;
		padding: 5px;

		&:hover {
			background-color: var(--color-background-hover);
			border-radius: var(--border-radius);
		}
	}

	.conversation__details {
		padding: 3px;
		overflow: hidden;
	}

	h3 {
		font-size: 100%;
		margin: 0;
	}

	.message {
		width: 100%;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
</style>
