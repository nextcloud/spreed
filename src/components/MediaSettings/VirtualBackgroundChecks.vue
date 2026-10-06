<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { FilesetResolver, ImageSegmenter } from '@mediapipe/tasks-vision'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconCheckAll from 'vue-material-design-icons/CheckAll.vue'
import IconInformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import IconSnail from 'vue-material-design-icons/Snail.vue'
import { getModelAssetPath, getWasmFileset } from '../../utils/media/effects/virtual-background/VideoStreamBackgroundEffect.js'
import VirtualBackground from '../../utils/media/pipeline/VirtualBackground.js'

type CheckStatus = 'checking' | 'ok' | 'slow' | 'error' | 'info'
type CheckResult = { status: CheckStatus, detail: string }
type CheckId = 'webAssembly' | 'segmentation' | 'rendering'

const CHECKING: CheckResult = { status: 'checking', detail: t('spreed', 'Checking …') }
const SKIPPED: CheckResult = { status: 'info', detail: '—' }
const NOT_SUPPORTED: CheckResult = { status: 'error', detail: t('spreed', 'Not supported') }

const CHECKS: { id: CheckId, label: string }[] = [
	{ id: 'webAssembly', label: 'WebAssembly' },
	{ id: 'segmentation', label: t('spreed', 'Segmentation') },
	{ id: 'rendering', label: t('spreed', 'Rendering') },
]

const results = ref<Partial<Record<CheckId, CheckResult>>>({})
const isRunning = ref(false)

const checks = computed(() => CHECKS.map((check) => ({ ...check, ...(results.value[check.id] ?? CHECKING) })))

/**
 * Check WebAssembly support and its variant
 */
async function checkWebAssembly(): Promise<CheckResult> {
	if (!VirtualBackground.isWasmSupported()) {
		return NOT_SUPPORTED
	}
	return await FilesetResolver.isSimdSupported()
		? { status: 'ok', detail: 'SIMD' }
		: { status: 'slow', detail: t('spreed', 'Without SIMD') }
}

/**
 * Check that a segmenter can be created with the GPU delegate (as used in calls)
 */
async function checkSegmentation(): Promise<CheckResult> {
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
	return { status: 'ok', detail: 'GPU' }
}

/**
 * Check which compositor is available
 */
function checkRendering(): CheckResult {
	if (VirtualBackground.isWebGLSupported()) {
		return { status: 'ok', detail: 'WebGL 2' }
	}
	if (VirtualBackground.isCanvasFilterSupported()) {
		return { status: 'slow', detail: 'Canvas 2D' }
	}
	return NOT_SUPPORTED
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

	setResult('rendering', checkRendering())

	const webAssembly = await checkWebAssembly()
	setResult('webAssembly', webAssembly)
	setResult('segmentation', webAssembly.status === 'error' ? SKIPPED : await checkSegmentation())

	isRunning.value = false
}
</script>

<template>
	<NcPopover @afterShow="runChecks">
		<template #trigger>
			<NcButton variant="tertiary" wide>
				<template #icon>
					<IconCheckAll :size="20" />
				</template>
				{{ t('spreed', 'Check browser support') }}
			</NcButton>
		</template>

		<div class="virtual-background-checks">
			<ul>
				<li
					v-for="check in checks"
					:key="check.id"
					class="virtual-background-checks__item">
					<NcLoadingIcon v-if="check.status === 'checking'" :size="20" />
					<IconCheck v-else-if="check.status === 'ok'" :size="20" fillColor="var(--color-border-success)" />
					<IconSnail v-else-if="check.status === 'slow'" :size="20" fillColor="var(--color-border-warning)" />
					<IconAlertCircleOutline v-else-if="check.status === 'error'" :size="20" fillColor="var(--color-border-error)" />
					<IconInformationOutline v-else :size="20" />
					<span class="virtual-background-checks__label">{{ check.label }}</span>
					<span class="virtual-background-checks__detail">{{ check.detail }}</span>
				</li>
			</ul>
			<NcNoteCard
				v-if="results.webAssembly?.status === 'error'"
				type="error"
				:text="t('spreed', 'Failed: WebAssembly is disabled or not supported in this browser. Please enable WebAssembly or use a browser with support for it to do the check.')" />
		</div>
	</NcPopover>
</template>

<style lang="scss" scoped>
.virtual-background-checks {
	padding: calc(var(--default-grid-baseline) * 2);

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
