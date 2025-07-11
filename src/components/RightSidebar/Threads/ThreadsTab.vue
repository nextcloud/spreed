<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { RouteLocation } from 'vue-router'

import { computed, onBeforeUnmount, onMounted } from 'vue'
import ThreadItem from './ThreadItem.vue'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { useIsInCall } from '../../../composables/useIsInCall.js'
import { EventBus } from '../../../services/EventBus.ts'
import { useChatExtrasStore } from '../../../stores/chatExtras.ts'

const emit = defineEmits<{
	(event: 'close'): void
}>()

const chatExtrasStore = useChatExtrasStore()
const isInCall = useIsInCall()
const token = useGetToken()
const threadsInformation = computed(() => chatExtrasStore.getThreadsList(token.value))

onMounted(() => {
	EventBus.on('route-change', onRouteChange)
})

onBeforeUnmount(() => {
	EventBus.off('route-change', onRouteChange)
})

const onRouteChange = ({ from, to }: { from: RouteLocation, to: RouteLocation }): void => {
	if (to.name !== 'conversation' || from.params.token !== to.params.token || (from.query.threadId !== to.query.threadId && isInCall.value)) {
		emit('close')
	}
}

</script>

<template>
	<ul class="threads-tab__list">
		<ThreadItem v-for="thread of threadsInformation"
			:key="`thread_${thread.thread.id}`"
			:thread="thread" />
	</ul>
</template>

<style lang="scss" scoped>
.threads-tab {
  &__list {
    transition: all 0.15s ease;
    height: 100%;
    overflow-y: auto;
  }
}
</style>
