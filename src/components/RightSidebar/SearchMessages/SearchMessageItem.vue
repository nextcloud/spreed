<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcListItem from '@nextcloud/vue/components/NcListItem'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'

const props = defineProps({
	messageId: {
		type: [Number, String],
		default: ''
	},
	title: {
		type: String,
		default: ''
	},
	to: {
		type: Object,
		default: () => ({})
	},
	subline: {
		type: String,
		default: ''
	},
	actorId: {
		type: String,
		default: ''
	},
	actorType: {
		type: String,
		default: ''
	},
	token: {
		type: String,
		default: ''
	},
	timestamp: {
		type: String,
		default: ''
	},
})
</script>

<template>
	<NcListItem :data-nav-id="`message_${messageId}`"
		:name="title"
		:to="to"
		:title="subline">
		<template #icon>
			<AvatarWrapper :id="actorId"
				:name="title"
				:source="actorType"
				disable-menu
				:token="token" />
		</template>
		<template #subname>
			{{ subline }}
		</template>
		<template #details>
			<NcDateTime :timestamp="parseInt(timestamp) * 1000"
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
