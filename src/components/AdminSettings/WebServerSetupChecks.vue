<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { FilesetResolver, ImageSegmenter } from '@mediapipe/tasks-vision'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconAlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconInformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import { getModelAssetPath, getWasmFileset } from '../../utils/media/effects/virtual-background/VideoStreamBackgroundEffect.js'
import VirtualBackground from '../../utils/media/pipeline/VirtualBackground.js'

type CheckStatus = 'checking' | 'ok' | 'warning' | 'error' | 'info'
type CheckResult = { status: CheckStatus, detail: string }
type CheckId = 'wasm' | 'tflite' | 'webAssembly' | 'simd' | 'webGL' | 'canvasFilter' | 'gpu' | 'cpu'
type GroupId = 'files' | 'client'
type CheckGroup = { id: GroupId, label: string, checks: { id: CheckId, label: string }[] }

const CHECKING: CheckResult = { status: 'checking', detail: t('spreed', 'Checking …') }
const SKIPPED: CheckResult = { status: 'info', detail: '—' }
const ERROR: CheckResult = { status: 'error', detail: t('spreed', 'Error') }

const apachePHPConfiguration = loadState<string>('spreed', 'valid_apache_php_configuration')

const CHECK_GROUPS: CheckGroup[] = [{
	id: 'files',
	label: t('spreed', 'Files required for virtual background can be loaded'),
	checks: [
		{ id: 'wasm', label: '.wasm' },
		{ id: 'tflite', label: '.tflite' },
	],
}, {
	id: 'client',
	label: t('spreed', 'This client supports virtual background'),
	checks: [
		{ id: 'webAssembly', label: 'WebAssembly' },
		{ id: 'simd', label: 'WebAssembly SIMD' },
		{ id: 'webGL', label: 'WebGL 2' },
		{ id: 'canvasFilter', label: 'Canvas 2D filter' },
		{ id: 'gpu', label: 'GPU' },
		{ id: 'cpu', label: 'CPU' },
	],
}]

const results = ref<Partial<Record<CheckId, CheckResult>>>({})

const groupNotes = computed<Record<GroupId, { type: 'error' | 'warning', text: string } | null>>(() => {
	const fileStatuses = [results.value.wasm?.status, results.value.tflite?.status]
	const filesType = fileStatuses.includes('error') ? 'error' : fileStatuses.includes('warning') ? 'warning' : null

	return {
		files: filesType
			? { type: filesType, text: t('spreed', 'Failed: ".wasm" and ".tflite" files were not properly returned by the web server. Please check "System requirements" section in Talk documentation.') }
			: null,
		client: results.value.webAssembly?.status === 'error'
			? { type: 'error', text: t('spreed', 'Failed: WebAssembly is disabled or not supported in this browser. Please enable WebAssembly or use a browser with support for it to do the check.') }
			: null,
	}
})

const checkGroups = computed(() => CHECK_GROUPS.map((group) => ({
	...group,
	note: groupNotes.value[group.id],
	checks: group.checks.map((check) => ({ ...check, ...(results.value[check.id] ?? CHECKING) })),
})))

const isChecking = computed(() => checkGroups.value.some((group) => group.checks.some((check) => check.status === 'checking')))

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
async function checkFile(url: string, expectedType?: string): Promise<CheckResult> {
	try {
		const response = await fetch(url)
		if (!response.ok) {
			console.error(`Failed to load ${url}: HTTP status ${response.status}`)
			return ERROR
		}
		// Read body, so the segmenter check can use the HTTP cache
		await response.arrayBuffer()
		const contentType = response.headers.get('Content-Type') ?? ''
		if (expectedType && !contentType.startsWith(expectedType)) {
			return {
				status: 'warning',
				detail: contentType || '—',
			}
		}
		return { status: 'ok', detail: t('spreed', 'OK') }
	} catch (error) {
		console.error(`Failed to load ${url}:`, error)
		return ERROR
	}
}

/**
 * Check that a segmenter can be created with the delegate
 *
 * @param delegate MediaPipe delegate
 */
async function checkSegmenter(delegate: 'GPU' | 'CPU'): Promise<CheckResult> {
	let segmenter: ImageSegmenter
	try {
		segmenter = await ImageSegmenter.createFromOptions(await getWasmFileset(), {
			baseOptions: {
				modelAssetPath: getModelAssetPath(),
				delegate,
			},
			runningMode: 'VIDEO',
			outputCategoryMask: false,
			outputConfidenceMasks: true,
		})
	} catch (error) {
		console.error(`Failed to create segmenter with ${delegate} delegate:`, error)
		return ERROR
	}

	try {
		segmenter.close()
	} catch (error) {
		console.warn(`Failed to close segmenter with ${delegate} delegate:`, error)
	}
	return { status: 'ok', detail: t('spreed', 'OK') }
}

/**
 * Map a boolean to a Yes/No result
 *
 * @param value check value
 * @param failStatus status for "No"
 */
function toResult(value: boolean, failStatus: CheckStatus = 'error'): CheckResult {
	return value
		? { status: 'ok', detail: t('spreed', 'Yes') }
		: { status: failStatus, detail: t('spreed', 'No') }
}

/**
 * Store a check result
 *
 * @param id check id
 * @param result check result
 */
function setResult(id: CheckId, result: CheckResult) {
	results.value = { ...results.value, [id]: result }
}

/**
 * Run all virtual background checks
 */
async function runChecks() {
	results.value = {}

	const wasmSupported = VirtualBackground.isWasmSupported()
	setResult('webAssembly', toResult(wasmSupported))
	setResult('webGL', toResult(VirtualBackground.isWebGLSupported(), 'warning'))
	setResult('canvasFilter', toResult(VirtualBackground.isCanvasFilterSupported(), 'warning'))

	if (!wasmSupported) {
		setResult('wasm', SKIPPED)
		setResult('simd', SKIPPED)
		setResult('gpu', SKIPPED)
		setResult('cpu', SKIPPED)
		setResult('tflite', await checkFile(getModelAssetPath()))
		return
	}

	try {
		const simdSupported = await FilesetResolver.isSimdSupported()
		setResult('simd', toResult(simdSupported, 'info'))

		// .wasm variant depends on SIMD support
		const fileset = await getWasmFileset()
		const fileResults = await Promise.all([
			checkFile(fileset.wasmBinaryPath, 'application/wasm').then((result) => {
				setResult('wasm', result)
				return result
			}),
			checkFile(getModelAssetPath()).then((result) => {
				setResult('tflite', result)
				return result
			}),
		])
		if (fileResults.some((result) => result.status === 'error')) {
			setResult('gpu', SKIPPED)
			setResult('cpu', SKIPPED)
			return
		}

		// Sequential to avoid two WASM instances at once
		setResult('gpu', await checkSegmenter('GPU'))
		setResult('cpu', await checkSegmenter('CPU'))
	} catch (error) {
		console.error('Failed to run virtual background checks:', error)
		for (const id of ['wasm', 'tflite', 'simd', 'gpu', 'cpu'] as const) {
			if (!results.value[id]) {
				setResult(id, SKIPPED)
			}
		}
	}
}
</script>

<template>
	<div id="web_server_setup_checks" class="section">
		<h2>
			{{ t('spreed', 'Web server setup checks') }}
		</h2>

		<NcNoteCard v-if="apacheWarning" :type="apacheWarningType" :text="apacheWarning" />

		<ul class="web-server-setup-checks">
			<li v-for="group in checkGroups" :key="group.id">
				{{ group.label }}
				<ul class="web-server-setup-checks__items">
					<li
						v-for="check in group.checks"
						:key="check.id"
						class="web-server-setup-checks__item">
						<NcLoadingIcon v-if="check.status === 'checking'" :size="20" />
						<IconCheck v-else-if="check.status === 'ok'" :size="20" fillColor="var(--color-border-success)" />
						<IconAlertOutline v-else-if="check.status === 'warning'" :size="20" fillColor="var(--color-border-warning)" />
						<IconAlertCircleOutline v-else-if="check.status === 'error'" :size="20" fillColor="var(--color-border-error)" />
						<IconInformationOutline v-else :size="20" />
						<span class="web-server-setup-checks__label">{{ check.label }}</span>
						<span class="web-server-setup-checks__detail">{{ check.detail }}</span>
					</li>
				</ul>
				<NcNoteCard
					v-if="group.note"
					:type="group.note.type"
					:text="group.note.text" />
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
