<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { FilesetResolver, ImageSegmenter } from '@mediapipe/tasks-vision'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconCheckAll from 'vue-material-design-icons/CheckAll.vue'
import IconSnail from 'vue-material-design-icons/Snail.vue'
import { getModelAssetPath, getWasmFileset } from '../../utils/media/effects/virtual-background/VideoStreamBackgroundEffect.js'
import VirtualBackground from '../../utils/media/pipeline/VirtualBackground.js'

type CheckStatus = 'checking' | 'ok' | 'slow' | 'error'
type CheckResult = { status: CheckStatus, detail: string }
type CheckId = 'segmentation' | 'compositing'

const CHECKING: CheckResult = { status: 'checking', detail: '' }

const STATUS_TEXT: Record<CheckStatus, string> = {
	checking: t('spreed', 'Checking …'),
	// TRANSLATORS: Virtual background check {status}, runs at best performance
	ok: t('spreed', 'Fast'),
	// TRANSLATORS: Virtual background check {status}, runs with a slower fallback
	slow: t('spreed', 'Slow'),
	error: t('spreed', 'Not supported'),
}

const CHECKS: { id: CheckId, label: (status: string) => string }[] = [
	// TRANSLATORS: Virtual background check, whether finding a person mask in the camera image is fast or slow
	{ id: 'segmentation', label: (status) => t('spreed', 'Person detecting: {status}', { status }) },
	// TRANSLATORS: Virtual background check, whether applying a replaced background is fast or slow
	{ id: 'compositing', label: (status) => t('spreed', 'Image compositing: {status}', { status }) },
]

const isWasmSupported = VirtualBackground.isWasmSupported()

const results = ref<Partial<Record<CheckId, CheckResult>>>({})
const isRunning = ref(false)

const checks = computed(() => CHECKS.map(({ id, label }) => {
	const { status, detail } = results.value[id] ?? CHECKING
	const statusText = detail
		// TRANSLATORS: Virtual background check result with technical details, e.g. "Fast (GPU)" or "Slow (GPU, no SIMD)"
		? t('spreed', '{status} ({detail})', { status: STATUS_TEXT[status], detail })
		: STATUS_TEXT[status]
	return { id, status, label: label(statusText) }
}))

/**
 * Check that a segmenter can be created with the GPU delegate (as used in calls)
 */
async function checkSegmentation(): Promise<CheckResult> {
	if (!isWasmSupported) {
		return { status: 'error', detail: t('spreed', 'WebAssembly is not available') }
	}

	let segmenter: ImageSegmenter
	try {
		segmenter = await ImageSegmenter.createFromOptions(await getWasmFileset(), {
			baseOptions: {
				modelAssetPath: getModelAssetPath(),
				delegate: 'GPU',
			},
			runningMode: 'VIDEO',
			outputCategoryMask: false,
			outputConfidenceMasks: true,
		})
	} catch (error) {
		console.error('Failed to create segmenter with GPU delegate:', error)
		return { status: 'error', detail: t('spreed', 'Error') }
	}

	try {
		segmenter.close()
	} catch (error) {
		console.warn('Failed to close segmenter with GPU delegate:', error)
	}
	// Without SIMD MediaPipe loads a slower WebAssembly build
	return await FilesetResolver.isSimdSupported()
		? { status: 'ok', detail: 'GPU' }
		: { status: 'slow', detail: 'GPU, no SIMD' }
}

/**
 * Check which compositor is available
 */
function checkCompositing(): CheckResult {
	if (VirtualBackground.isWebGLSupported()) {
		return { status: 'ok', detail: 'WebGL 2' }
	}
	if (VirtualBackground.isCanvasFilterSupported()) {
		return { status: 'slow', detail: 'Canvas 2D' }
	}
	return { status: 'error', detail: '' }
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
 * Run all virtual background client checks
 */
async function runChecks() {
	if (isRunning.value) {
		return
	}
	isRunning.value = true
	results.value = {}

	setResult('compositing', checkCompositing())
	setResult('segmentation', await checkSegmentation())

	isRunning.value = false
}

/**
 * Clear finished results, so the next open starts from "Checking"
 */
function resetResults() {
	if (!isRunning.value) {
		results.value = {}
	}
}

/**
 * Copy the check results and browser info to the clipboard
 */
async function copyReport() {
	if (checks.value.some(({ status }) => status === 'checking')) {
		return
	}

	const report = [
		...checks.value.map(({ label }) => label),
		navigator.userAgent,
	].join('\n')

	try {
		await navigator.clipboard.writeText(report)
		showSuccess(t('spreed', 'Report copied to clipboard'))
	} catch (error) {
		showError(t('spreed', 'Report could not be copied'))
	}
}
</script>

<template>
	<NcPopover @afterShow="runChecks" @afterHide="resetResults">
		<template #trigger>
			<NcButton variant="tertiary" wide>
				<template #icon>
					<IconCheckAll :size="20" />
				</template>
				{{ t('spreed', 'Check device support') }}
			</NcButton>
		</template>

		<div class="virtual-background-checks">
			<NcFormBox>
				<NcFormBoxButton
					v-for="check in checks"
					:key="check.id"
					:label="check.label"
					@click="copyReport">
					<template #icon>
						<NcLoadingIcon v-if="check.status === 'checking'" :size="20" />
						<IconCheck v-else-if="check.status === 'ok'" :size="20" fillColor="var(--color-border-success)" />
						<IconSnail v-else-if="check.status === 'slow'" :size="20" fillColor="var(--color-border-warning)" />
						<IconAlertCircleOutline v-else :size="20" fillColor="var(--color-border-error)" />
					</template>
				</NcFormBoxButton>
			</NcFormBox>
			<NcNoteCard
				v-if="!isWasmSupported"
				type="error"
				:text="t('spreed', 'Failed: WebAssembly is disabled or not supported in this browser. Please enable WebAssembly or use a browser with support for it to do the check.')" />
		</div>
	</NcPopover>
</template>

<style lang="scss" scoped>
.virtual-background-checks {
	padding: calc(var(--default-grid-baseline) * 2);
}
</style>
