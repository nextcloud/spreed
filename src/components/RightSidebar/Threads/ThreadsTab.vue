<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'
import ThreadItem from './ThreadItem.vue'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { useChatExtrasStore } from '../../../stores/chatExtras.ts'

const chatExtrasStore = useChatExtrasStore()

const token = useGetToken()
const threadsInformation = computed(() => chatExtrasStore.getThreadsList(token.value))
</script>

<template>
	<div class="threads-tab">
		<ul class="threads-tab__list">
			<ThreadItem v-for="thread of threadsInformation"
				:key="`thread_${thread.thread.id}`"
				:thread="thread" />
		</ul>
	</div>
</template>

<style lang="scss" scoped>
.threads-tab {
  display: flex;
  flex-direction: column;
  height: 100%;

  &__list {
    transition: all 0.15s ease;
    height: 100%;
    overflow-y: auto;
  }
}
</style>
