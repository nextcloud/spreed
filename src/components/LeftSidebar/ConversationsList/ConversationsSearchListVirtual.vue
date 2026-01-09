<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../../types/index.ts'

import { useVirtualList } from '@vueuse/core'
import { toRef } from 'vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'
import ConversationSearchResult from './ConversationSearchResult.vue'
import { AVATAR } from '../../../constants.ts'

const props = defineProps<{
	conversations: Conversation[]
	loading?: boolean
}>()

const emit = defineEmits<{
	select: [item: Conversation]
}>()

const itemHeight = AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2

const { list, containerProps, wrapperProps } = useVirtualList<Conversation>(toRef(() => props.conversations), {
	itemHeight,
	overscan: 10,
})

/**
 * Pass selected conversation to parent component
 *
 * @param item - selected conversation
 */
function handleClick(item: Conversation) {
	emit('select', item)
}
</script>

<template>
	<li
		:ref="containerProps.ref"
		:style="containerProps.style"
		@scroll="containerProps.onScroll">
		<LoadingPlaceholder v-if="loading" type="conversations" />
		<ul
			v-else
			:style="wrapperProps.style">
			<ConversationSearchResult
				v-for="item in list"
				:key="item.data.id"
				:item="item.data"
				@click="handleClick" />
		</ul>
	</li>
</template>
