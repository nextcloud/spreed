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
	<NcModal size="normal"
		:container="container"
		@close="close">
		<div id="modal-inner" class="talk-modal" :class="{ 'icon-loading': loading }">
			<div id="modal-content">
				<h2>
					{{ dialogTitle }}
				</h2>
				<p v-if="dialogSubtitle" class="subtitle">
					{{ dialogSubtitle }}
				</p>
				<NcTextField :value.sync="searchText"
					trailing-button-icon="close"
					class="search-form"
					:label="t('spreed', 'Search conversations or users')"
					:show-trailing-button="searchText !==''"
					@trailing-button-click="clearText">
					<Magnify :size="16" />
				</NcTextField>
				<div id="room-list">
					<ul v-if="!loading && availableRooms.length > 0">
						<li v-for="room in availableRooms"
							:key="room.token"
							:class="{selected: selectedRoom?.token === room.token }"
							@click="selectedRoom = room">
							<ConversationIcon :item="room" :hide-favorite="false" />
							<span>{{ room.displayName }}</span>
						</li>
					</ul>
					<div v-else-if="!loading" class="no-match-message">
						<h2 class="no-match-title">
							{{ noMatchFoundTitle }}
						</h2>
						<p v-if="noMatchFoundSubtitle" class="subtitle">
							{{ noMatchFoundSubtitle }}
						</p>
					</div>
				</div>
				<div id="modal-buttons">
					<NcButton v-if="!loading && availableRooms.length > 0"
						type="primary"
						:disabled="!selectedRoom"
						@click="select">
						{{ t('spreed', 'Select conversation') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import Magnify from 'vue-material-design-icons/Magnify.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import ConversationIcon from './ConversationIcon.vue'

import { CONVERSATION } from '../constants.js'
import { searchListedConversations, fetchConversations } from '../services/conversationsService.js'

export default {
	name: 'RoomSelector',
	components: {
		ConversationIcon,
		NcModal,
		NcButton,
		NcTextField,
		Magnify,
	},
	props: {
		container: {
			type: String,
			default: undefined,
		},

		dialogTitle: {
			type: String,
			default: t('spreed', 'Link to a conversation'),
		},

		dialogSubtitle: {
			type: String,
			default: '',
		},

		/**
		 * Whether to only show conversations to which
		 * the user can post messages.
		 */
		showPostableOnly: {
			type: Boolean,
			default: false,
		},

		listOpenConversations: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close', 'select'],
	data() {
		return {
			rooms: [],
			selectedRoom: null,
			currentRoom: null,
			searchText: '',
			loading: true,
		}
	},
	computed: {
		availableRooms() {
			const roomsTemp = this.rooms.filter((room) => {
				return room.type !== CONVERSATION.TYPE.CHANGELOG
					&& (!this.currentRoom || this.currentRoom !== room.token)
					&& (!this.showPostableOnly || room.readOnly === CONVERSATION.STATE.READ_WRITE)
					&& room.objectType !== CONVERSATION.OBJECT_TYPE.FILE
					&& room.objectType !== CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION
			})
			if (!this.searchText) {
				return roomsTemp
			} else {
				return roomsTemp.filter(room => room.displayName.toLowerCase().includes(this.searchText.toLowerCase()))
			}
		},

		noMatchFoundTitle() {
			return this.listOpenConversations
				? t('spreed', 'No open conversations found')
				: t('spreed', 'No conversations found')
		},

		noMatchFoundSubtitle() {
			return this.listOpenConversations
				? t('spreed', 'Either there are no open conversations or you joined all of them.')
				: t('spreed', 'Check spelling or use complete words.')
		},
	},
	beforeMount() {
		this.fetchRooms()
		const $store = OCA.Talk?.instance?.$store
		if ($store) {
			this.currentRoom = $store.getters.getToken()
		}
	},
	methods: {
		async fetchRooms() {
			const response = this.listOpenConversations
				? await searchListedConversations({ searchText: '' }, {})
				: await fetchConversations({})

			this.rooms = response.data.ocs.data.sort(this.sortConversations)
			this.loading = false
		},
		sortConversations(conversation1, conversation2) {
			if (conversation1.isFavorite !== conversation2.isFavorite) {
				return conversation1.isFavorite ? -1 : 1
			}

			return conversation2.lastActivity - conversation1.lastActivity
		},

		clearText() {
			this.searchText = ''
		},

		close() {
			// FIXME: should not emit on $root but on itself
			this.$root.$emit('close')
			this.$emit('close')
		},
		select() {
			this.$root.$emit('select', this.selectedRoom)
			this.$emit('select', this.selectedRoom)
		},
	},
}
</script>

<style lang="scss" scoped>

:deep(.modal-container) {
	height: 700px;
}

/* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/4350 regression is solved */
/* Force modal close button to be above modal content */
:deep(.modal-container__close) {
	z-index: 1;
}

.talk-modal {
	height: 80vh;
}

#modal-inner {
	width: 100%;
	padding: 16px;
	margin: 0 auto;
	position: relative;
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	box-sizing: border-box;

	h2 {
		margin-bottom: 4px;
	}
}

#modal-content {
	position: absolute;
	width: calc(100% - 40px);
	height: calc(100% - 40px);
	display: flex;
	flex-direction: column;
}

#room-list {
	overflow-y: auto;
	flex: 0 1 auto;
	height: 100%;
}

.no-match-message {
	padding: 40px 0;
	text-align: center;

}

.no-match-title {
	font-weight: normal;
}

li {
	padding: 6px;
	border: 1px solid transparent;
	display: flex;

	&:hover,
	&:focus {
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius-pill);
	}

	&.selected {
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius-pill);
	}

	& > span {
		padding: 8px 5px 8px 10px;
		vertical-align: middle;
		text-overflow: ellipsis;
		white-space: nowrap;
		overflow: hidden;
	}
}

#modal-buttons {
	overflow: hidden;
	flex-shrink: 0;
	margin-left: auto;
}

.subtitle {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}

.search-form {
	margin-bottom: 10px;
}
</style>
