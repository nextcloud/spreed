<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { MatrixAccount, MatrixHomeserver } from '../../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { computed, onMounted, reactive, ref } from 'vue'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconLinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import IconLinkVariantOff from 'vue-material-design-icons/LinkVariantOff.vue'
import ConfirmDialog from '../UIShared/ConfirmDialog.vue'
import { getMatrixAccount, linkMatrixAccount, unlinkMatrixAccount } from '../../services/matrixService.ts'
import { isAxiosErrorResponse } from '../../types/guards.ts'

const LINK_ERRORS: Record<string, string> = {
	credentials: t('spreed', 'Wrong Matrix username or password'),
	unreachable: t('spreed', 'The homeserver could not be reached'),
	user: t('spreed', 'The Matrix user does not belong to the selected homeserver'),
	'already-linked': t('spreed', 'A Matrix account is already linked'),
	'not-allowed': t('spreed', 'You are not allowed to link a Matrix account'),
	homeserver: t('spreed', 'The selected homeserver is no longer available'),
}

const loaded = ref(false)
const loading = ref(false)
const canLink = ref(false)
const account = ref<MatrixAccount | null>(null)
const homeservers = ref<MatrixHomeserver[]>([])
const form = reactive<{ homeserver: MatrixHomeserver | null, user: string, password: string }>({
	homeserver: null,
	user: '',
	password: '',
})

const accountDescription = computed(() => {
	if (!account.value) {
		return ''
	}

	return [
		// TRANSLATORS: {mxid} is the Matrix user ID, e.g. @alice:example.org
		t('spreed', 'Linked as {mxid}', { mxid: account.value.mxid }),
		// TRANSLATORS: {deviceId} is the ID of the device this client uses on the Matrix account
		t('spreed', 'Device {deviceId}', { deviceId: account.value.deviceId }),
	].join(' · ')
})

onMounted(loadMatrixDetails)

/**
 * Load the linked account and the homeservers available for linking
 */
async function loadMatrixDetails() {
	try {
		const response = await getMatrixAccount()
		canLink.value = response.data.ocs.data.canLink
		account.value = response.data.ocs.data.account
		homeservers.value = response.data.ocs.data.homeservers
		form.homeserver = homeservers.value[0] ?? null
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not load the Matrix account'))
	}
	loaded.value = true
}

/**
 * Log in on the selected homeserver and link the account
 */
async function linkAccount() {
	if (loading.value || !form.homeserver || !form.user || !form.password) {
		return
	}

	loading.value = true
	try {
		const response = await linkMatrixAccount({
			homeserverId: form.homeserver.id,
			user: form.user,
			password: form.password,
		})
		account.value = response.data.ocs.data
		form.user = ''
	} catch (error) {
		console.error(error)
		const reason = isAxiosErrorResponse<{ error: string }>(error) ? error.response?.data?.ocs?.data?.error : undefined
		showError((reason && LINK_ERRORS[reason]) || t('spreed', 'Could not link the Matrix account'))
	} finally {
		form.password = ''
		loading.value = false
	}
}

/**
 * Ask for confirmation, then unlink the account and log this client out
 */
async function unlinkAccount() {
	const confirmUnlinkAccount = await spawnDialog(ConfirmDialog, {
		// TRANSLATORS: Dialog title and button to unlink the Matrix account from this client
		name: t('spreed', 'Unlink account'),
		message: t('spreed', 'Do you really want to unlink "{mxid}"? This client will be logged out from your Matrix account.', {
			mxid: account.value!.mxid,
		}, { escape: false, sanitize: false }),
		buttons: [
			{ label: t('spreed', 'No'), variant: 'tertiary', callback: () => undefined },
			{ label: t('spreed', 'Yes'), variant: 'error', callback: () => true },
		],
	})

	if (!confirmUnlinkAccount) {
		return
	}

	loading.value = true
	try {
		await unlinkMatrixAccount()
		account.value = null
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not unlink the Matrix account'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<div v-if="loaded" class="matrix-account">
		<NcFormGroup
			v-if="account"
			:label="t('spreed', 'Connected Matrix account')"
			:description="accountDescription">
			<NcFormBox>
				<NcFormBoxButton
					:disabled="loading"
					@click="unlinkAccount">
					<!-- TRANSLATORS: Dialog title and button to unlink the Matrix account from this client -->
					{{ t('spreed', 'Unlink account') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<IconLinkVariantOff v-else :size="20" />
					</template>
				</NcFormBoxButton>
			</NcFormBox>
		</NcFormGroup>

		<p v-else-if="!canLink || homeservers.length === 0" class="matrix-account__hint">
			{{ t('spreed', 'Linking a Matrix account is not available for you.') }}
		</p>

		<NcFormGroup
			v-else
			:label="t('spreed', 'Link account')"
			:description="t('spreed', 'Your password is only used once to log in and is not stored. This client appears as a new device on your Matrix account.')">
			<NcFormBox>
				<NcSelect
					v-if="homeservers.length > 1"
					v-model="form.homeserver"
					:inputLabel="t('spreed', 'Homeserver')"
					:options="homeservers"
					label="name"
					:clearable="false"
					:disabled="loading" />
				<NcTextField
					v-model="form.user"
					:label="t('spreed', 'Matrix username')"
					:placeholder="form.homeserver ? '@user:' + form.homeserver.serverName : ''"
					autocomplete="username"
					:disabled="loading"
					@keydown.enter="linkAccount" />
				<NcPasswordField
					v-model="form.password"
					:label="t('spreed', 'Matrix password')"
					autocomplete="current-password"
					:disabled="loading"
					@keydown.enter="linkAccount" />
				<NcFormBoxButton
					:disabled="loading || !form.homeserver || !form.user || !form.password"
					@click="linkAccount">
					<!-- TRANSLATORS: Section title and button to log in and link the Matrix account to this client -->
					{{ t('spreed', 'Link account') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<IconLinkVariant v-else :size="20" />
					</template>
				</NcFormBoxButton>
			</NcFormBox>
		</NcFormGroup>
	</div>
</template>

<style lang="scss" scoped>
.matrix-account {
	// Same as in NcFormGroup, it is not defined globally
	--form-element-label-offset: calc(var(--border-radius-element) + var(--default-grid-baseline));

	&__hint {
		padding-inline: var(--form-element-label-offset);
		color: var(--color-text-maxcontrast);
	}
}
</style>
