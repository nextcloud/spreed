<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcHeaderButton id="talk-sidebar-trigger"
		:title="ariaLabel"
		:aria-label="ariaLabel"
		@click="emit('click')">
		<template #icon>
			<IconMessageTextOutline :size="20" />
		</template>
	</NcHeaderButton>
</template>

<script setup lang="ts">
import type { UnwrapNestedRefs } from 'vue'

import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcHeaderButton from '@nextcloud/vue/components/NcHeaderButton'
import IconMessageTextOutline from 'vue-material-design-icons/MessageTextOutline.vue'

const props = defineProps<{
	sidebarState: UnwrapNestedRefs<{ isOpen: boolean }>
}>()

const emit = defineEmits<{
	(event: 'click'): void
}>()

const ariaLabel = computed(() => {
	return props.sidebarState.isOpen
		? t('spreed', 'Close Talk sidebar')
		: t('spreed', 'Open Talk sidebar')
})
</script>

<style scoped>
#talk-sidebar-trigger {
	margin-inline-start: var(--default-grid-baseline);
}
</style>
