<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { n, t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import { useChatExtrasStore } from '../../stores/chatExtras.ts'

const chatExtrasStore = useChatExtrasStore()

const tasksCount = computed(() => chatExtrasStore.tasksCount)
const tasksDoneCount = computed(() => chatExtrasStore.tasksDoneCount)

const tasksRatio = computed(() => {
	if (tasksCount.value === 0) {
		return 0
	}
	return (tasksDoneCount.value / tasksCount.value) * 100
})

const tasksSummary = computed(() => {
	if (tasksRatio.value === 100) {
		return t('spreed', 'All tasks done!')
	}
	// TRANSLATORS number of tasks done of total number of tasks
	return n('spreed', '{done} of %n task', '{done} of %n tasks', tasksCount.value, { done: tasksDoneCount.value })
})

</script>

<template>
	<div v-if="tasksCount" class="tasks-counter">
		<NcProgressBar type="circular" :value="tasksRatio" :color="tasksRatio === 100 ? 'var(--color-border-success)' : undefined" />
		<div class="tasks-counter__count">
			{{ tasksSummary }}
		</div>
	</div>
</template>

<style lang="scss" scoped>
.tasks-counter {
	display: flex;
	align-items: center;
	margin-inline: calc(var(--default-grid-baseline) * 2);

	&__count {
		font-weight: 500;
	}
}

</style>
