<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'
import { computed } from 'vue'
import { getTalkConfig } from '../../../services/CapabilitiesManager.ts'

const { effect, aspectRatio = null, compact = false, alignStart = false } = defineProps<{
	effect: string
	// Set when the video is letterboxed ("object-fit: contain")
	aspectRatio?: number | null
	compact?: boolean
	// Place the video area at the top start instead of the center
	alignStart?: boolean
}>()

const isLabelShown = getTalkConfig('local', 'call', 'ai-modified-label') !== false
const labelUrl = computed(() => imagePath('spreed', compact ? 'label-ai.svg' : 'label-ai-modified.svg'))
</script>

<template>
	<div
		v-if="isLabelShown"
		class="ai-modified-label"
		:class="{ 'ai-modified-label--fit': aspectRatio, 'ai-modified-label--start': alignStart }">
		<img
			class="ai-modified-label__image"
			:src="labelUrl"
			:alt="t('spreed', 'Video modified by AI')"
			:data-effect="effect">
	</div>
</template>

<style lang="scss" scoped>
// Covers the visible video area; the parent must set "container-type: size"
.ai-modified-label {
	position: absolute;
	inset: 0;
	width: 100cqw;
	height: 100cqh;
	margin: auto;
	container-type: size;
	pointer-events: none;
	opacity: 0.7;

	&--fit {
		width: min(100cqw, 100cqh * v-bind(aspectRatio));
		height: min(100cqh, 100cqw / v-bind(aspectRatio));
	}

	&--start {
		margin: 0;
	}

	&__image {
		position: absolute;
		top: 2.4cqh;
		inset-inline-start: 2.4cqh;
		height: clamp(18px, 6cqh, calc(var(--default-grid-baseline) * 6));
	}
}
</style>
