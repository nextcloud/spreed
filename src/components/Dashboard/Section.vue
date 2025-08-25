<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'

const { wide = false, title = '', subtitle = '', description = '' } = defineProps<{
	wide?: boolean
	title?: string
	subtitle?: string
	description?: string
}>()
const isMobile = useIsMobile()
</script>

<template>
	<div class="dashboard-section"
		:class="{
			'dashboard-section--wide': wide,
			'dashboard-section--list': $slots.list,
		}">
		<div v-if="!isMobile"
			class="dashboard-section__bar"
			:class="{
				'dashboard-section__bar--narrow': $slots.list,
				gradient: !$slots.image,
				'image-container': $slots.image,
			}">
			<slot name="image" />
		</div>
		<div class="dashboard-section__content">
			<h3 class="dashboard-section__title">
				{{ title }}
			</h3>
			<span class="dashboard-section__subtitle">{{ subtitle }}</span>
			<span class="dashboard-section__description">{{ description }}</span>
			<slot name="list" />
		</div>
		<div v-if="$slots.action" class="dashboard-section__action">
			<slot name="action" />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.dashboard-section {
	display: flex;
	border-radius: var(--border-radius-large);
	overflow: hidden;
	box-shadow: 0 2px 10px 0 rgba(0,0,0, 0.2);
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
		height: 96%; // bar is 4%
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
	}

	&__subtitle {
		font-weight: bold;
	}

	&__action {
		margin-block: auto;
		padding-inline: calc(var(--default-grid-baseline) * 2);
	}
}

h3 {
	margin-block: calc(var(--default-grid-baseline) * 2);
}
</style>
