<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { useSettingsStore } from '../../stores/settings.ts'
import { readableNumber } from '../../utils/readableNumber.ts'

const props = defineProps<{
	conversation: Conversation
}>()

const settingStore = useSettingsStore()

const dialInInfo = computed(() => settingStore.dialInInfo)
const meetingId = computed(() => readableNumber(props.conversation.token))
const attendeePin = computed(() => readableNumber(props.conversation.attendeePin!))
</script>

<template>
	<div class="sip-settings">
		<h3>{{ t('spreed', 'Dial-in information') }}</h3>
		<p>{{ dialInInfo }}</p>

		<h3>{{ t('spreed', 'Meeting ID') }}</h3>
		<p>{{ meetingId }}</p>

		<h3>{{ t('spreed', 'Your PIN') }}</h3>
		<p>{{ attendeePin }}</p>
	</div>
</template>

<style lang="scss" scoped>
.sip-settings {
	h3 {
		margin-bottom: 6px;
		font-weight: bold;
	}

	p {
		white-space: pre-line;
	}
}
</style>
