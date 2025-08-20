<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useStore } from 'vuex'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconNetworkStrength2Alert from 'vue-material-design-icons/NetworkStrength2Alert.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { CONVERSATION } from '../../constants.ts'
import { EventBus } from '../../services/EventBus.ts'

const store = useStore()
const isInCall = useIsInCall()
const token = useGetToken()

const isGroupConversation = computed(() => {
	return [CONVERSATION.TYPE.GROUP, CONVERSATION.TYPE.PUBLIC].includes(store.getters.conversation(token.value)?.type)
})
const show = ref(false)

const warningDescription = t('spreed', 'Calls without High-performance backend can cause connectivity issues and high load on devices. {linkstart}Learn more{linkend}')
	.replace('{linkstart}', '<a target="_blank" rel="noreferrer nofollow" class="external" href="https://portal.nextcloud.com/article/Nextcloud-Talk/High-Performance-Backend/Installation-of-Nextcloud-Talk-High-Performance-Backend">')
	.replace('{linkend}', ' ↗</a>')

const warningTitle = t('spreed', 'Talk setup incomplete')

onMounted(() => {
	EventBus.on('signaling-internal-show-warning', showInternalWarning)
})

onBeforeUnmount(() => {
	EventBus.on('signaling-internal-show-warning', showInternalWarning)
})

watch(isInCall, (value) => {
	if (!value) {
		show.value = false
	}
})

const showInternalWarning = () => {
	if (isGroupConversation.value) {
		show.value = true
	}
}
</script>

<template>
	<NcNoteCard v-show="show" type="warning" class="internal-warning">
		<template #icon>
			<IconNetworkStrength2Alert fill-color="var(--color-warning-text)" :size="20" />
		</template>
		<strong>{{ warningTitle }}</strong>
		<!-- eslint-disable-next-line vue/no-v-html -->
		<p v-html="warningDescription" />
	</NcNoteCard>
</template>

<style lang="scss" scoped>
.internal-warning {
	margin: var(--default-grid-baseline) !important;
}
</style>
