<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import IconReload from 'vue-material-design-icons/Reload.vue'

const props = defineProps<{
	name: string | null
	start: string | null
	color: string
	isRecurring?: boolean
	href?: string
}>()
</script>

<template>
	<li class="calendar-event">
		<a
			class="calendar-event__item"
			:class="{ 'calendar-event__item--thumb': !href }"
			:href="href"
			:title="t('spreed', 'Open Calendar')"
			:tabindex="0"
			target="_blank">
			<span class="calendar-event__badge" :style="{ backgroundColor: color }" />
			<span class="calendar-event__content">
				<span class="calendar-event__header">
					<span class="calendar-event__header-text">{{ name }}</span>
					<IconReload v-if="isRecurring" :size="13" />
				</span>
				<span>{{ start }}</span>
			</span>
		</a>
	</li>
</template>

<style lang="scss" scoped>
.calendar-event {
	&__item {
		display: flex;
		flex-direction: row;
		align-items: center;
		margin-block: var(--default-grid-baseline);
		padding-inline: var(--default-grid-baseline);
		height: 100%;
		border-radius: var(--border-radius);

		&--thumb {
			cursor: default;
		}

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	&__badge {
		flex-shrink: 0;
		display: inline-block;
		width: var(--default-font-size);
		height: var(--default-font-size);
		margin-inline: calc((var(--default-clickable-area) - var(--default-font-size)) / 2);
		border-radius: 50%;
		background-color: var(--primary-color);
	}

	&__content {
		display: flex;
		flex-direction: column;
		justify-content: center;
		width: calc(100% - var(--default-clickable-area));
	}

	&__header {
		display: flex;
		gap: var(--default-grid-baseline);
		font-weight: 500;

		&-text {
			display: inline-block;
			width: 100%;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		:deep(.material-design-icon) {
			margin-top: 2px;
		}
	}
}
</style>
