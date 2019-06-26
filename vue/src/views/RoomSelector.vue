<!--
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<modal @close="close">
		<div id="modal-inner" :class="{ 'icon-loading': loading }">
			<div id="modal-content">
				<h1>{{ t('spreed', 'Select a conversation to add to the project') }}</h1>
				<div id="room-list">
					<ul v-if="!loading">
						<li v-for="room in availableRooms" :key="room.token" :class="{selected: selectedRoom === room.token }"
							@click="selectedRoom=room.token">
							<avatar v-if="room.type === types.ROOM_TYPE_ONE_TO_ONE" :user="room.name" />
							<div v-else-if="room.type === types.ROOM_TYPE_PUBLIC" class="avatar icon icon-public icon-white" />
							<div v-else class="avatar icon icon-contacts" />
							<span>{{ room.displayName }}</span>
						</li>
					</ul>
				</div>
				<div id="modal-buttons">
					<button v-if="!loading" class="primary" @click="select">
						{{ t('spreed', 'Select conversation') }}
					</button>
				</div>
			</div>
		</div>
	</modal>
</template>
<style scoped>
	#modal-inner {
		width: 90vw;
		max-width: 400px;
		height: 50vh;
		position: relative;
	}
	#modal-content {
		position: absolute;
		width: calc(100% - 40px);
		height: calc(100% - 40px);
		display: flex;
		flex-direction: column;
		padding: 20px;
	}
	#room-list {
		overflow-y: scroll;
		flex: 0 1 auto;
	}
	li {
		padding: 6px;
		border: 1px solid transparent;
		display: flex;
	}
	li:hover, li:focus {
		background-color: var(--color-background-dark);
	}
	li.selected {
		box-shadow: inset 4px 0 var(--color-primary);
	}
	.avatar.icon {
		border-radius: 50%;
		width: 32px;
		height: 32px;
		background-color: var(--color-background-darker);
	}
	li > span {
		padding: 5px;
	}
	li > span,
	.avatar {
		vertical-align: middle;

	}
	#modal-buttons {
		overflow: hidden;
		height: 44px;
		flex-shrink: 0;
	}
	#modal-buttons .primary {
		float: right;
	}

</style>
<script>
/* global OC */
import { Modal } from 'nextcloud-vue/dist/Components/Modal'
import { Avatar } from 'nextcloud-vue/dist/Components/Avatar'
import axios from 'nextcloud-axios'

export default {
	name: 'CollaborationView',
	components: {
		Modal, Avatar
	},
	data() {
		return {
			rooms: [],
			selectedRoom: null,
			loading: true,
			// TODO: should be included once this is properly available
			types: {
				ROOM_TYPE_ONE_TO_ONE: 1,
				ROOM_TYPE_GROUP: 2,
				ROOM_TYPE_PUBLIC: 3,
				ROOM_TYPE_CHANGELOG: 4
			}
		}
	},
	computed: {
		currentRoom() {
			if (OCA.SpreedMe && OCA.SpreedMe.app.activeRoom) {
				return OCA.SpreedMe.app.activeRoom.get('token')
			}
			return null
		},
		availableRooms() {
			return this.rooms.filter((room) => {
				return room.token !== this.currentRoom
					&& room.type !== this.types.ROOM_TYPE_CHANGELOG
					&& room.objectType !== 'file'
					&& room.objectType !== 'share:password'
			})
		}
	},
	beforeMount() {
		this.fetchRooms()
	},
	methods: {
		fetchRooms() {
			axios.get(OC.linkToOCS('/apps/spreed/api/v1', 2) + 'room').then((response) => {
				this.rooms = response.data.ocs.data
				this.loading = false
			})
		},
		close() {
			this.$root.$emit('close')
		},
		select() {
			this.$root.$emit('select', this.selectedRoom)
		}
	}
}
</script>
