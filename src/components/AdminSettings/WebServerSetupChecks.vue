<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconAlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { getModelAssetPath, getWasmFileset } from '../../utils/media/effects/virtual-background/VideoStreamBackgroundEffect.js'

type CheckStatus = 'checking' | 'ok' | 'warning' | 'error'
type CheckResult = { status: CheckStatus, detail: string }
type CheckId = 'wasm' | 'tflite'

const CHECKING: CheckResult = { status: 'checking', detail: t('spreed', 'Checking …') }
const ERROR: CheckResult = { status: 'error', detail: t('spreed', 'Error') }
const FILES_FAILED_TEXT = t('spreed', 'Failed: ".wasm" and ".tflite" files were not properly returned by the web server. Please check "System requirements" section in Talk documentation.')

const CHECKS: { id: CheckId, label: string }[] = [
	{ id: 'wasm', label: '.wasm' },
	{ id: 'tflite', label: '.tflite' },
]

const apachePHPConfiguration = loadState<string>('spreed', 'valid_apache_php_configuration')

const results = ref<Record<CheckId, CheckResult>>({ wasm: CHECKING, tflite: CHECKING })

const checks = computed(() => CHECKS.map((check) => ({ ...check, ...results.value[check.id] })))

const isChecking = computed(() => checks.value.some((check) => check.status === 'checking'))

const noteType = computed(() => {
	const statuses = checks.value.map((check) => check.status)
	if (statuses.includes('error')) {
		return 'error'
	}
	if (statuses.includes('warning')) {
		return 'warning'
	}
	return null
})

const apacheWarning = computed(() => {
	if (apachePHPConfiguration === 'invalid') {
		return t('spreed', 'It seems that the PHP and Apache configuration is not compatible. Please note that PHP can only be used with the MPM_PREFORK module and PHP-FPM can only be used with the MPM_EVENT module.')
	}
	// Disabling this for now as there were too many false catches (VMs, AIO, nginx, permissions issue, …)
	// if (apachePHPConfiguration === 'unknown') {
	// return t('spreed', 'Could not detect the PHP and Apache configuration because exec is disabled or apachectl is not working as expected. Please note that PHP can only be used with the MPM_PREFORK module and PHP-FPM can only be used with the MPM_EVENT module.')
	// }
	return ''
})

const apacheWarningType = computed(() => {
	if (apachePHPConfiguration === 'invalid') {
		return 'error'
	}
	return 'warning'
})

runChecks()

/**
 * Check that the web server returns the file
 *
 * @param url file URL
 * @param expectedType expected MIME type (mismatch is a warning with the actual type)
 */
async function checkFile(url: string | Promise<string>, expectedType?: string): Promise<CheckResult> {
	try {
		// Bypass the HTTP cache to check the current server state
		const response = await fetch(await url, { cache: 'no-store' })
		// Only headers are needed, do not fetch the full file
		response.body?.cancel()
		if (!response.ok) {
			console.error(`Failed to load ${response.url}: HTTP status ${response.status}`)
			return ERROR
		}
		const contentType = response.headers.get('Content-Type') ?? ''
		if (expectedType && !contentType.startsWith(expectedType)) {
			return { status: 'warning', detail: contentType || '—' }
		}
		return { status: 'ok', detail: t('spreed', 'OK') }
	} catch (error) {
		console.error('Failed to load file:', error)
		return ERROR
	}
}

/**
 * Run all virtual background file checks
 */
async function runChecks() {
	results.value = { wasm: CHECKING, tflite: CHECKING }

	// .wasm variant depends on SIMD support
	const [wasm, tflite] = await Promise.all([
		checkFile(getWasmFileset().then((fileset) => fileset.wasmBinaryPath), 'application/wasm'),
		checkFile(getModelAssetPath()),
	])
	results.value = { wasm, tflite }
}
</script>

<template>
	<div id="web_server_setup_checks" class="section">
		<h2>
			{{ t('spreed', 'Web server setup checks') }}
		</h2>

		<NcNoteCard v-if="apacheWarning" :type="apacheWarningType" :text="apacheWarning" />

		<ul class="web-server-setup-checks">
			<li>
				{{ t('spreed', 'Files required for virtual background can be loaded') }}
				<ul class="web-server-setup-checks__items">
					<li
						v-for="check in checks"
						:key="check.id"
						class="web-server-setup-checks__item">
						<NcLoadingIcon v-if="check.status === 'checking'" :size="20" />
						<IconCheck v-else-if="check.status === 'ok'" :size="20" fillColor="var(--color-border-success)" />
						<IconAlertOutline v-else-if="check.status === 'warning'" :size="20" fillColor="var(--color-border-warning)" />
						<IconAlertCircleOutline v-else :size="20" fillColor="var(--color-border-error)" />
						<span class="web-server-setup-checks__label">{{ check.label }}</span>
						<span class="web-server-setup-checks__detail">{{ check.detail }}</span>
					</li>
				</ul>
				<NcNoteCard
					v-if="noteType"
					:type="noteType"
					:text="FILES_FAILED_TEXT" />
			</li>
		</ul>

		<NcButton :disabled="isChecking" @click="runChecks">
			{{ t('spreed', 'Test') }}
		</NcButton>
	</div>
</template>

<style lang="scss" scoped>
.web-server-setup-checks {
	margin-bottom: calc(var(--default-grid-baseline) * 3);

	&__items {
		margin-block: var(--default-grid-baseline);
		margin-inline-start: calc(var(--default-grid-baseline) * 4);
	}

	&__item {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__label {
		font-weight: bold;
	}

	&__detail {
		color: var(--color-text-maxcontrast);
	}
}
</style>
