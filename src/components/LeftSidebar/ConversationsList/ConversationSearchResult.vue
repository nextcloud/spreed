<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem :key="item.token"
		:name="item.displayName"
		:title="item.displayName"
		:active="item.token === selectedRoom?.token"
		:bold="exposeMessagesRef && !!item.unreadMessages"
		:counter-number="exposeMessagesRef ? item.unreadMessages : 0"
		:counter-type="counterType"
		@click="onClick">
		<template #icon>
			<ConversationIcon :item="item" :hide-favorite="!item?.attendeeId" :hide-call="!item?.attendeeId" />
		</template>
		<template v-if="conversationInformation" #subname>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<span v-html="conversationInformation" />
		</template>
	</NcListItem>
</template>

<script>
import { inject, toRefs, ref } from 'vue'

import NcListItem from '@nextcloud/vue/components/NcListItem'

import ConversationIcon from './../../ConversationIcon.vue'

import { useConversationInfo } from '../../../composables/useConversationInfo.ts'

export default {
	name: 'ConversationSearchResult',

	components: {
		ConversationIcon,
		NcListItem,
	},

	props: {
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
				}
			},
		},
	},

	emits: ['click'],

	setup(props) {
		const { item } = toRefs(props)
		const selectedRoom = inject('selectedRoom', null)
		const exposeDescriptionRef = inject('exposeDescription', ref(false))
		const exposeMessagesRef = inject('exposeMessages', ref(false))
		const { counterType, conversationInformation } = useConversationInfo({
			item,
			exposeDescriptionRef,
			exposeMessagesRef,
		})

		return {
			selectedRoom,
			counterType,
			conversationInformation,
			exposeMessagesRef,
		}
	},

	methods: {
		onClick() {
			this.$emit('click', this.item)
		},
	},
}
</script>
