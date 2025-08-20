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
			:class="{ 'dashboard-section__bar--narrow': $slots.list }" />
		<div class="dashboard-section__content-wrapper">
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
	</div>
</template>

<style lang="scss" scoped>
.dashboard-section {
    display: flex;
    border-radius: var(--border-radius-large);
    overflow: hidden;
    box-shadow: 0 2px 10px 0 rgba(0,0,0, 0.2);
    height: 100%;
    flex-direction: column;

    &--wide {
        flex-direction: row;
    }

    &__content-wrapper {
        display: flex;
        flex: auto;
        height: 96%; // bar is 4%
        padding: var(--default-grid-baseline);
    }

    &__content {
        position: relative;
        display: flex;
        flex-direction: column;
        flex-grow: inherit;
        padding: 0 calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
    }

    &__bar {
        background: linear-gradient(100deg, var(--color-primary) 0%, var(--color-main-background) 130%);
        flex: 0 0 50%;

        &--narrow {
           flex: 0 0 4%;
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
       margin-top: auto;
    }
}

h3 {
    margin-block: calc(var(--default-grid-baseline) * 2);
}
</style>
