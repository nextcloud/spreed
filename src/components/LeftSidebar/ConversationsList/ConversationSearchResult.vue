<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem :key="item.token"
		:name="item.displayName"
		:title="item.displayName"
		:active="item.token === selectedRoom?.token"
		:bold="exposeMessages && !!item.unreadMessages"
		:counter-number="exposeMessages ? item.unreadMessages : 0"
		:counter-type="counterType"
		@click="onClick">
		<template #icon>
			<ConversationIcon :item="item" :hide-favorite="!item?.attendeeId" :hide-call="!item?.attendeeId" />
		</template>
		<template v-if="conversationInformation" #subname>
			{{ conversationInformation }}
		</template>
	</NcListItem>
</template>

<script>
import { inject, toRefs } from 'vue'

import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import ConversationIcon from './../../ConversationIcon.vue'

import { useConversationInfo } from '../../../composables/useConversationInfo.js'

export default {
	name: 'ConversationSearchResult',

	components: {
		ConversationIcon,
		NcListItem,
	},

	props: {
		exposeMessages: {
			type: Boolean,
			default: false,
		},
		item: {
			type: Object,
			default() {
				return {
					token: '',
					participants: [],
					participantType: 0,
					unreadMessages: 0,
					unreadMention: false,
					objectType: '',
					type: 0,
					displayName: '',
					isFavorite: false,
					notificationLevel: 0,
					lastMessage: {},
				}
			},
		},
	},

	emits: ['click'],

	setup(props) {
		const { item, exposeMessages } = toRefs(props)
		const selectedRoom = inject('selectedRoom', null)
		const { counterType, conversationInformation } = useConversationInfo({ item, exposeMessages })

		return {
			selectedRoom,
			counterType,
			conversationInformation,
		}
	},

	methods: {
		onClick() {
			this.$emit('click', this.item)
		},
	},
}
</script>
