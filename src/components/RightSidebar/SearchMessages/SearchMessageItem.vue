<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../../types/index.ts'

import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { computed } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import ConversationIcon from '../../ConversationIcon.vue'
import { useStore } from '../../../composables/useStore.js'
import { CONVERSATION } from '../../../constants.ts'
import { useDashboardStore } from '../../../stores/dashboard.ts'

const props = defineProps({
	messageId: {
		type: [Number, String],
		default: '',
	},
	title: {
		type: String,
		default: '',
	},
	to: {
		type: Object,
		default: () => ({}),
	},
	subline: {
		type: String,
		default: '',
	},
	actorId: {
		type: String,
		default: '',
	},
	actorType: {
		type: String,
		default: '',
	},
	token: {
		type: String,
		default: '',
	},
	timestamp: {
		type: String,
		default: '',
	},
	messageParameters: {
		type: [Array, Object],
		default: () => ([]),
	},
	isReminder: {
		type: Boolean,
		default: false,
	},
})

const store = useStore()
const dashboardStore = useDashboardStore()

const conversation = computed<Conversation | undefined>(() => store.getters.conversation(props.token))
const isOneToOneConversation = computed(() => conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE)
const name = computed(() => {
	if (!props.isReminder || isOneToOneConversation.value) {
		return props.title
	}
	return t('spreed', '{actor} in {conversation}', { actor: props.title, conversation: conversation.value?.displayName ?? '' }, { escape: false, sanitize: false })
})
const richSubline = computed(() => {
	if (!props.isReminder || !props.messageParameters || Array.isArray(props.messageParameters)) {
		return props.subline
	}

	let text = props.subline.trim()

	// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
	Object.entries(props.messageParameters).forEach(([key, value]) => {
		text = text.replaceAll('{' + key + '}', value.name)
	})

	return text
})
const clearReminderLabel = computed(() => {
	if (!props.isReminder) {
		return ''
	}
	return t('spreed', 'Clear reminder – {timeLocale}', { timeLocale: moment(+props.timestamp * 1000).format('ddd LT') })
})
</script>

<template>
	<NcListItem :data-nav-id="`message_${messageId}`"
		:name="name"
		:to="to"
		:title="richSubline"
		force-menu>
		<template #icon>
			<AvatarWrapper v-if="!isReminder || isOneToOneConversation"
				:id="actorId"
				:name="title"
				:source="actorType"
				disable-menu
				:token="token" />
			<ConversationIcon v-else
				:item="conversation"
				hide-user-status />
		</template>
		<template #subname>
			{{ richSubline }}
		</template>
		<template v-if="isReminder" #actions>
			<NcActionButton close-after-click
				@click.stop="dashboardStore.removeReminder(token, +messageId)">
				<template #icon>
					<CloseCircleOutline :size="20" />
				</template>
				{{ clearReminderLabel }}
			</NcActionButton>
		</template>
		<template #details>
			<NcDateTime :timestamp="+timestamp * 1000"
				class="search-results__date"
				relative-time="narrow"
				ignore-seconds />
		</template>
	</NcListItem>
</template>

<style lang="scss" scoped>
.search-results__date {
	font-size: x-small;
}
</style>
