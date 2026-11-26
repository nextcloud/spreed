<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem
		:name="item.displayName"
		:title="item.displayName"
		:active="item.token === selectedRoom?.token"
		:bold="exposeMessagesRef && !!item.unreadMessages"
		:counterNumber="exposeMessagesRef ? item.unreadMessages : 0"
		:counterType
		@click="onClick">
		<template #icon>
			<ConversationIcon
				:key="item.token"
				:item
				:hideFavorite="!item?.attendeeId" />
		</template>
		<template #name>
			<template v-if="item.attendeeId && item.hasCall">
				<IconVideo :size="15" fillColor="#E9322D" />
				<span class="hidden-visually">{{ t('spreed', 'Call in progress') }}</span>
			</template>
			<span class="text"> {{ item.displayName }} </span>
		</template>
		<template v-if="conversationInformation.message" #subname>
			<span class="conversation__subname" :title="conversationInformation.title">
				<span
					v-if="conversationInformation.actor"
					class="conversation__subname-actor">
					{{ conversationInformation.actor }}
				</span>
				<component
					:is="conversationInformation.icon"
					v-if="conversationInformation.icon"
					class="conversation__subname-icon"
					:size="16" />
				<span class="conversation__subname-message">
					{{ conversationInformation.message }}
				</span>
			</span>
		</template>
	</NcListItem>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { inject, ref, toRefs } from 'vue'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconVideo from 'vue-material-design-icons/Video.vue' // Filled for better indication
import ConversationIcon from './../../ConversationIcon.vue'
import { useConversationInfo } from '../../../composables/useConversationInfo.ts'

export default {
	name: 'ConversationSearchResult',

	components: {
		ConversationIcon,
		IconVideo,
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
		t,

		onClick() {
			this.$emit('click', this.item)
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.list-item-content__name) {
	display: flex;
	gap: calc(var(--default-grid-baseline) / 2);
}

.text {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.conversation__subname {
	display: flex;
	gap: var(--default-grid-baseline);

	&-actor {
		flex: 0 1 auto;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	&-icon {
		flex-shrink: 0;
	}
	&-message {
		flex: 1 1 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}
</style>
