<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Participant } from '../../../types/index.ts'

import { useVirtualList } from '@vueuse/core'
import { computed, toRef } from 'vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'
import ParticipantItem from './ParticipantItem.vue'
import { AVATAR } from '../../../constants.ts'

const props = defineProps<{
	participants: Participant[]
	loading?: boolean
}>()

/* Consider:
 * avatar size (and two lines of text)
 * list-item padding
 * list-item__wrapper padding
 */
const itemHeight = AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2

const { list, containerProps, wrapperProps } = useVirtualList<Participant>(toRef(() => props.participants), {
	itemHeight,
	overscan: 10,
})

const count = computed(() => props.loading ? Math.max(6 - props.participants.length, 0) : 0)
</script>

<template>
	<li
		:ref="containerProps.ref"
		:style="containerProps.style"
		@scroll="containerProps.onScroll">
		<LoadingPlaceholder v-if="loading" type="participants" :count />
		<ul
			v-else
			:style="wrapperProps.style">
			<ParticipantItem
				v-for="item in list"
				:key="item.data.attendeeId"
				:participant="item.data" />
		</ul>
	</li>
</template>
