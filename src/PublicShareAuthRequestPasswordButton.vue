<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { getSharingToken } from '@nextcloud/sharing/public'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconTalkApp from '../img/app.svg?raw'
import { getPublicShareAuthConversationToken } from './services/filesIntegrationServices.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'

const tokenStore = useTokenStore()

const loading = ref(false)
const failed = ref(false)

const isVerificationInProgress = computed(() => loading.value || !!tokenStore.token)

/**
 * Creates a conversation with share owner and triggers sidebar initialization to sar the call
 */
async function requestVideoVerification() {
	checkBrowser()

	try {
		failed.value = false
		loading.value = true
		const response = await getPublicShareAuthConversationToken(getSharingToken()!)

		tokenStore.updateToken(response.data.ocs.data.token)
	} catch (exception) {
		failed.value = true
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<NcNoteCard v-if="failed" type="warning">
		{{ t('spreed', 'Error requesting the password.') }}
	</NcNoteCard>
	<NcButton
		id="request-password-button"
		class="request-password-button"
		variant="primary"
		wide
		:disabled="isVerificationInProgress"
		@click="requestVideoVerification">
		<template #icon>
			<NcLoadingIcon v-if="isVerificationInProgress" :size="20" />
			<NcIconSvgWrapper v-else :svg="IconTalkApp" :size="20" />
		</template>
		{{ t('spreed', 'Request password') }}
	</NcButton>
</template>

<style lang="scss" scoped>
.request-password-button {
	margin-top: calc(2 * var(--default-grid-baseline));
}
</style>
