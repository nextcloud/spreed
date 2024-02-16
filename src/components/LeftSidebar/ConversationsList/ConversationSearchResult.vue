<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
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
	<NcListItem :key="item.token"
		:name="item.displayName"
		:title="item.displayName"
		:active="item.token === selectedRoom"
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
