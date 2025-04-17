<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="dialogTitle"
		close-on-click-outside
		@update:open="close">
		<template #default>
			<p v-if="dialogSubtitle" class="selector__subtitle">
				{{ dialogSubtitle }}
			</p>
			<NcTextField v-model="searchText"
				trailing-button-icon="close"
				class="selector__search"
				:label="t('spreed', 'Search conversations or users')"
				:show-trailing-button="searchText !==''"
				@trailing-button-click="clearText">
				<template #icon>
					<Magnify :size="16" />
				</template>
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
		</template>

		<template #actions>
			<NcButton v-if="!loading && availableRooms.length > 0"
				class="selector__action"
				type="primary"
				:disabled="!selectedRoom"
				@click="onSubmit">
				{{ t('spreed', 'Select conversation') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { provide, ref } from 'vue'

import Magnify from 'vue-material-design-icons/Magnify.vue'
import MessageOutline from 'vue-material-design-icons/MessageOutline.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import ConversationsSearchListVirtual from './LeftSidebar/ConversationsList/ConversationsSearchListVirtual.vue'

import { CONVERSATION } from '../constants.ts'
import { searchListedConversations, fetchConversations } from '../services/conversationsService.ts'

export default {
	name: 'RoomSelector',

	components: {
		ConversationsSearchListVirtual,
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcTextField,
		// Icons
		Magnify,
		MessageOutline,
	},

	props: {
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
		 * Whether interacting with federated conversations is allowed for this component.
		 */
		allowFederation: {
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

		/**
		 * Whether component is used as plugin and should emit on $root.
		 */
		isPlugin: {
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
		t,
		async fetchRooms() {
			const response = this.listOpenConversations
				? await searchListedConversations('')
				: await fetchConversations({
					includeStatus: 1,
				})

			this.rooms = response.data.ocs.data.sort(this.sortConversations)
				// Federated conversations do not support:
				// - open conversations
				// - 3rd app integrations (e.g. Deck / Maps)
				.filter(conversation => this.allowFederation || !conversation.remoteServer)
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
			if (this.isPlugin) {
				this.$root.$emit('close')
			} else {
				this.$emit('close')
			}
		},

		onSelect(item) {
			this.selectedRoom = item
		},

		onSubmit() {
			if (this.isPlugin) {
				this.$root.$emit('select', this.selectedRoom)
			} else {
				this.$emit('select', this.selectedRoom)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.modal-wrapper .modal-container) {
	height: 700px;
}

:deep(.modal-wrapper .dialog__content) {
	width: 100%;
	height: 100%;
	display: flex;
	flex-direction: column;
}

.selector {
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
		margin-inline-start: auto;
	}
}
</style>
