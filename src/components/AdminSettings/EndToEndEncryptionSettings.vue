<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { useAdminSettingsState } from '../../composables/useAdminSettingsState.ts'
import { setAppConfig } from '../../services/appConfigService.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'

const { hasFeatureJoinFeatures, hasSIPBridge } = useAdminSettingsState()

const loading = ref(false)
const isE2EECallsEnabled = ref(!!getTalkConfig('local', 'call', 'end-to-end-encryption'))

const canEnableE2EECalls = computed(() => hasFeatureJoinFeatures.value || !hasSIPBridge.value)

/**
 * Enable or disable end-to-end encrypted calls
 *
 * @param value - new value
 */
async function saveE2EECallsEnabled(value: boolean) {
	loading.value = true
	try {
		await setAppConfig('spreed', 'call_end_to_end_encryption', value ? '1' : '0')
		isE2EECallsEnabled.value = value
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save the setting'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<section id="e2ee_settings" class="e2ee section">
		<h2>{{ t('spreed', 'End-to-end encrypted calls') }}</h2>

		<div class="e2ee__form">
			<NcFormBox>
				<NcFormBoxSwitch
					:modelValue="isE2EECallsEnabled"
					:label="t('spreed', 'Enable encryption')"
					:description="t('spreed', 'Not supported on mobile clients before v26')"
					:disabled="loading || !canEnableE2EECalls"
					@update:modelValue="saveE2EECallsEnabled" />
			</NcFormBox>

			<NcNoteCard
				v-if="!canEnableE2EECalls"
				type="warning"
				:text="t('spreed', 'End-to-end encrypted calls with a configured SIP bridge require a newer version of the High-performance backend and SIP bridge.')" />
		</div>
	</section>
</template>

<style lang="scss" scoped>
// TODO apply the 600px width limit to all admin settings sections
.e2ee {
	// Same as in NcFormGroup, it is not defined globally
	--form-element-label-offset: calc(var(--border-radius-element) + var(--default-grid-baseline));

	h2 {
		max-width: 600px;
		padding-inline: var(--form-element-label-offset);
	}
}

.e2ee__form {
	display: flex;
	flex-direction: column;
	gap: calc(2.5 * var(--default-grid-baseline));
	max-width: 600px;
}
</style>
