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
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconLanConnect from 'vue-material-design-icons/LanConnect.vue'
import IconLinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import IconLinkVariantOff from 'vue-material-design-icons/LinkVariantOff.vue'
import IconLogin from 'vue-material-design-icons/Login.vue'
import ConfirmDialog from '../UIShared/ConfirmDialog.vue'
import { useActionStatus } from '../../composables/useActionStatus.ts'
import { MATRIX } from '../../constants.ts'
import { checkMatrixConnection, getMatrixAccount, linkMatrixAccount, reloginMatrixAccount, unlinkMatrixAccount } from '../../services/matrixService.ts'
import { isAxiosErrorResponse } from '../../types/guards.ts'

const LINK_ERRORS: Record<string, string> = {
	credentials: t('spreed', 'Wrong Matrix username or password'),
	unreachable: t('spreed', 'The homeserver could not be reached'),
	user: t('spreed', 'The Matrix user does not belong to the selected homeserver'),
	'already-linked': t('spreed', 'A Matrix account is already linked'),
	'not-allowed': t('spreed', 'You are not allowed to link a Matrix account'),
	homeserver: t('spreed', 'The selected homeserver is no longer available'),
}

const RELOGIN_ERRORS: Record<string, string> = {
	...LINK_ERRORS,
	user: t('spreed', 'The login belongs to a different Matrix account'),
}

const { getActionStatus, runAction } = useActionStatus()

const loaded = ref(false)
const loading = ref(false)
const canLink = ref(false)
const account = ref<MatrixAccount | null>(null)
const connected = ref(true)
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
		connected.value = response.data.ocs.data.connected
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
		connected.value = true
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
 * Log in again after the homeserver rejected the access token
 */
async function reloginAccount() {
	if (loading.value || !form.password) {
		return
	}

	loading.value = true
	try {
		const response = await reloginMatrixAccount({ password: form.password })
		account.value = response.data.ocs.data
		connected.value = true
	} catch (error) {
		console.error(error)
		const reason = isAxiosErrorResponse<{ error: string }>(error) ? error.response?.data?.ocs?.data?.error : undefined
		showError((reason && RELOGIN_ERRORS[reason]) || t('spreed', 'Could not log in to Matrix again'))
	} finally {
		form.password = ''
		loading.value = false
	}
}

/**
 * Check the connection to the homeserver again
 */
async function checkConnection() {
	if (loading.value) {
		return
	}

	loading.value = true
	let checked = false
	try {
		await runAction('connection', async () => {
			const response = await checkMatrixConnection()
			checked = true
			account.value = response.data.ocs.data.account
			connected.value = response.data.ocs.data.connected
			if (!connected.value) {
				throw new Error('The homeserver could not be reached')
			}
		})
	} catch (error) {
		console.error(error)
		showError(checked
			? t('spreed', 'The homeserver could not be reached')
			: t('spreed', 'Could not check the connection to the homeserver'))
	} finally {
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
		message: t('spreed', 'Do you really want to unlink "{mxid}"? This client will be logged out of your Matrix account.', {
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
			<template v-if="account.status === MATRIX.ACCOUNT_STATUS.TOKEN_INVALID">
				<p class="matrix-account__warning">
					{{ t('spreed', 'The homeserver ended the session of this client. Enter your Matrix password to log in again.') }}
				</p>
				<p v-if="account.lastError" class="matrix-account__hint">
					{{ account.lastError }}
				</p>
			</template>
			<NcFormBox>
				<template v-if="account.status === MATRIX.ACCOUNT_STATUS.TOKEN_INVALID">
					<NcPasswordField
						v-model="form.password"
						:label="t('spreed', 'Matrix password')"
						autocomplete="current-password"
						:disabled="loading"
						@keydown.enter="reloginAccount" />
					<NcFormBoxButton
						:disabled="loading || !form.password"
						@click="reloginAccount">
						<!-- TRANSLATORS: Button to log in to Matrix again with the password -->
						{{ t('spreed', 'Log in') }}
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
							<IconLogin v-else :size="20" />
						</template>
					</NcFormBoxButton>
				</template>
				<NcFormBoxButton
					v-else-if="account.status === MATRIX.ACCOUNT_STATUS.ACTIVE"
					:label="t('spreed', 'Test connection')"
					:disabled="loading"
					@click="checkConnection">
					<template #icon>
						<NcLoadingIcon v-if="getActionStatus('connection') === 'pending'" :size="20" />
						<IconCheck v-else-if="getActionStatus('connection') === 'success'" :size="20" fillColor="var(--color-border-success)" />
						<IconAlertCircleOutline v-else-if="getActionStatus('connection') === 'error' || !connected" :size="20" fillColor="var(--color-border-error)" />
						<IconLanConnect v-else :size="20" />
					</template>
				</NcFormBoxButton>
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
			{{ t('spreed', 'Linking a Matrix account is not available to you.') }}
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

	&__warning {
		padding-inline: var(--form-element-label-offset);
		color: var(--color-text-error);
	}
}
</style>
