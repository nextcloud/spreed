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
	<NcModal size="small" :container="container" @close="close">
		<div class="selector">
			<!-- Heading, search field -->
			<h2 class="selector__heading">
				{{ dialogTitle }}
			</h2>
			<p v-if="dialogSubtitle" class="selector__subtitle">
				{{ dialogSubtitle }}
			</p>
			<NcTextField :value.sync="searchText"
				trailing-button-icon="close"
				class="selector__search"
				:label="t('spreed', 'Search conversations or users')"
				:show-trailing-button="searchText !==''"
				@trailing-button-click="clearText">
				<Magnify :size="16" />
			</NcTextField>

			<!-- Conversations list-->
			<ConversationsSearchListVirtual v-if="loading || availableRooms.length > 0"
				:conversations="availableRooms"
				:loading="loading"
				class="selector__list"
				@select="onSelect" />
			<NcEmptyContent v-else :name="noMatchFoundTitle" :description="noMatchFoundSubtitle">
				<template #icon>
					<MessageOutline :size="64" />
				</template>
			</NcEmptyContent>

			<!-- Actions -->
			<NcButton v-if="!loading && availableRooms.length > 0"
				class="selector__action"
				type="primary"
				:disabled="!selectedRoom"
				@click="onSubmit">
				{{ t('spreed', 'Select conversation') }}
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import { provide, ref } from 'vue'

import Magnify from 'vue-material-design-icons/Magnify.vue'
import MessageOutline from 'vue-material-design-icons/MessageOutline.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import ConversationsSearchListVirtual from './LeftSidebar/ConversationsList/ConversationsSearchListVirtual.vue'

import { CONVERSATION } from '../constants.js'
import { searchListedConversations, fetchConversations } from '../services/conversationsService.js'

export default {
	name: 'RoomSelector',

	components: {
		ConversationsSearchListVirtual,
		NcButton,
		NcEmptyContent,
		NcModal,
		NcTextField,
		// Icons
		Magnify,
		MessageOutline,
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
		 * Whether to only show conversations to which the user can post messages.
		 */
		showPostableOnly: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether to only show open conversations to which the user can join.
		 */
		listOpenConversations: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'select'],

	setup() {
		const selectedRoom = ref(null)
		provide('selectedRoom', selectedRoom)

		return {
			selectedRoom,
		}
	},

	data() {
		return {
			rooms: [],
			currentRoom: null,
			searchText: '',
			loading: true,
		}
	},

	computed: {
		availableRooms() {
			return this.rooms.filter(room => room.type !== CONVERSATION.TYPE.CHANGELOG
				&& room.objectType !== CONVERSATION.OBJECT_TYPE.FILE
				&& room.objectType !== CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION
				&& (!this.currentRoom || this.currentRoom !== room.token)
				&& (!this.showPostableOnly || room.readOnly === CONVERSATION.STATE.READ_WRITE)
				&& (!this.searchText || room.displayName.toLowerCase().includes(this.searchText.toLowerCase()))
			)
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

		onSelect(item) {
			this.selectedRoom = item
		},

		onSubmit() {
			// FIXME: should not emit on $root but on itself
			this.$root.$emit('select', this.selectedRoom)
			this.$emit('select', this.selectedRoom)
		},
	},
}
</script>

<style lang="scss" scoped>
/* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/4959 is released */
/* Styles to be applied outside of Talk (Deck plugin, e.t.c) */
:deep(.modal-wrapper *) {
	box-sizing: border-box;
}

:deep(.modal-wrapper .modal-container) {
	height: 700px;
}

.selector {
	width: 100%;
	height: 100%;
	display: flex;
	flex-direction: column;
	padding: 16px;

	&__heading {
		margin-bottom: 4px;
	}

	&__subtitle {
		color: var(--color-text-maxcontrast);
		margin-bottom: 8px;
	}

	&__search {
		margin-bottom: 10px;
	}

	&__list {
		height: 100%;
	}

	&__action {
		flex-shrink: 0;
		margin-left: auto;
	}
}
</style>
