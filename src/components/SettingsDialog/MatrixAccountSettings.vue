<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { MatrixAccount, MatrixHomeserver } from '../../types/index.ts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { getMatrixAccount, linkMatrixAccount, unlinkMatrixAccount } from '../../services/matrixService.ts'

const LINK_ERRORS: Record<string, string> = {
	credentials: t('spreed', 'Wrong Matrix username or password'),
	unreachable: t('spreed', 'The homeserver could not be reached'),
	user: t('spreed', 'The Matrix user does not belong to the selected homeserver'),
	'already-linked': t('spreed', 'A Matrix account is already linked'),
	'not-allowed': t('spreed', 'You are not allowed to link a Matrix account'),
}

const loaded = ref(false)
const loading = ref(false)
const canLink = ref(false)
const account = ref<MatrixAccount | null>(null)
const homeservers = ref<MatrixHomeserver[]>([])
const homeserver = ref<MatrixHomeserver | null>(null)
const user = ref('')
const password = ref('')

onMounted(load)

/**
 * Load the linked account and the homeservers available for linking
 */
async function load() {
	try {
		const response = await getMatrixAccount()
		canLink.value = response.data.ocs.data.canLink
		account.value = response.data.ocs.data.account
		homeservers.value = response.data.ocs.data.homeservers
		homeserver.value = homeservers.value[0] ?? null
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not load the Matrix account'))
	}
	loaded.value = true
}

/**
 * Log in on the selected homeserver and link the account
 */
async function link() {
	if (!homeserver.value || !user.value || !password.value) {
		return
	}

	loading.value = true
	try {
		const response = await linkMatrixAccount({
			homeserverId: homeserver.value.id,
			user: user.value,
			password: password.value,
		})
		account.value = response.data.ocs.data
		user.value = ''
		showSuccess(t('spreed', 'Matrix account linked'))
	} catch (error) {
		console.error(error)
		// @ts-expect-error Vue: Object is of type unknown
		const reason = error?.response?.data?.ocs?.data?.error
		showError(LINK_ERRORS[reason] ?? t('spreed', 'Could not link the Matrix account'))
	} finally {
		password.value = ''
		loading.value = false
	}
}

/**
 * Unlink the account and log Talk out on the homeserver
 */
async function unlink() {
	loading.value = true
	try {
		await unlinkMatrixAccount()
		account.value = null
		showSuccess(t('spreed', 'Matrix account unlinked'))
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
		<template v-if="account">
			<p>
				{{ t('spreed', 'Linked as {mxid}', { mxid: account.mxid }) }}
				<span class="matrix-account__muted">{{ t('spreed', 'Device {device}', { device: account.deviceId }) }}</span>
			</p>
			<NcButton :disabled="loading" @click="unlink">
				{{ t('spreed', 'Unlink Matrix account') }}
			</NcButton>
		</template>

		<p v-else-if="!canLink || homeservers.length === 0" class="matrix-account__muted">
			{{ t('spreed', 'Linking a Matrix account is not available for you.') }}
		</p>

		<form v-else class="matrix-account__form" @submit.prevent="link">
			<p>
				{{ t('spreed', 'Your password is only used once to log in and is not stored. Talk appears as a new device on your Matrix account.') }}
			</p>
			<NcSelect
				v-if="homeservers.length > 1"
				v-model="homeserver"
				:inputLabel="t('spreed', 'Homeserver')"
				:options="homeservers"
				label="name"
				:clearable="false"
				:disabled="loading" />
			<NcTextField
				v-model="user"
				:label="t('spreed', 'Matrix username')"
				:placeholder="homeserver ? '@user:' + homeserver.serverName : ''"
				autocomplete="username"
				:disabled="loading" />
			<NcPasswordField
				v-model="password"
				:label="t('spreed', 'Matrix password')"
				autocomplete="current-password"
				:disabled="loading" />
			<NcButton
				type="submit"
				variant="primary"
				:disabled="loading || !homeserver || !user || !password">
				{{ t('spreed', 'Link account') }}
			</NcButton>
		</form>
	</div>
</template>

<style lang="scss" scoped>
.matrix-account {
	&__form {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
		max-width: 400px;
	}

	&__muted {
		color: var(--color-text-maxcontrast);
	}
}
</style>
