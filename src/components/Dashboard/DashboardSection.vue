<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import { useIsMobile, useIsSmallMobile } from '@nextcloud/vue/composables/useIsMobile'

const { wide = false, title = '', subtitle = '', description = '' } = defineProps<{
	wide?: boolean
	title?: string
	subtitle?: string
	description?: string
}>()
const isSmallMobile = useIsSmallMobile()
const isMobile = useIsMobile()
</script>

<template>
	<div class="dashboard-section"
		:class="{
			'dashboard-section--wide': wide && !isSmallMobile,
			'dashboard-section--list': $slots.list,
		}">
		<div v-if="!isSmallMobile"
			class="dashboard-section__bar"
			:class="{
				'dashboard-section__bar--narrow': $slots.list || isMobile,
				gradient: !$slots.image || isMobile,
				'image-container': $slots.image,
			}">
			<slot v-if="!($slots.list || isMobile)" name="image" />
		</div>
		<div class="dashboard-section__content">
			<h3 class="dashboard-section__title">
				{{ title }}
			</h3>
			<span class="dashboard-section__subtitle">{{ subtitle }}</span>
			<span class="dashboard-section__description">{{ description }}</span>
			<slot name="list" />
			<div v-if="$slots.action" class="dashboard-section__action">
				<slot name="action" />
			</div>
		</div>
	</div>
</template>

<style lang="scss" scoped>
.dashboard-section {
	display: flex;
	border-radius: var(--border-radius-large);
	overflow: hidden;
	border: 2px solid var(--color-border);
	height: 100%;

	&--wide {
		flex-direction: row;

		.dashboard-section__content {
			justify-content: center;
		}
	}

	&__content {
		position: relative;
		display: flex;
		flex-direction: column;
		flex: auto;
		min-height: 0;
		padding: 0 calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 5);
	}

	&__bar {
		flex: 0 0 200px;

		&.gradient {
			background: linear-gradient(78deg, var(--color-primary) 60%, var(--color-main-background) 120%);
		}

		&--narrow {
			flex: 0 0 10px;
		}

		// Style for slotted images
		:deep(img) {
			width: 100%;
			height: 100%;
			object-fit: cover;
			object-position: center;
		}

		&.image-container {
			display: flex;
			align-items: center;
			justify-content: center;
			overflow: hidden;
		}
	}

	&__title {
		font-size: 1.25rem;
		font-weight: bold;
		overflow-wrap: break-word;
	}

	&__subtitle {
		font-weight: bold;
	}

	&__action {
		padding-block: calc(var(--default-grid-baseline) * 2);
	}
}

h3 {
	margin-block: calc(var(--default-grid-baseline) * 2);
}
</style>
