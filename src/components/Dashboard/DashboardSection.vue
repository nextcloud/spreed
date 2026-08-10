<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import { useIsMobile, useIsSmallMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed } from 'vue'

const {
	wide = false,
	title = '',
	subtitle = '',
	description = '',
	backgroundImage = '',
	backgroundImageDark = '',
} = defineProps<{
	wide?: boolean
	title?: string
	subtitle?: string
	description?: string
	/** Illustration to use as the background of the whole section */
	backgroundImage?: string
	/** Variant of backgroundImage for dark themes. Falls back to backgroundImage when not given */
	backgroundImageDark?: string
}>()
const isSmallMobile = useIsSmallMobile()
const isMobile = useIsMobile()

const backgroundStyle = computed(() => {
	if (!backgroundImage) {
		return undefined
	}
	return {
		'--dashboard-section-image-light': `url('${backgroundImage}')`,
		// Left unset when there is no dark variant, so that the stylesheet can fall back
		...(backgroundImageDark && { '--dashboard-section-image-dark': `url('${backgroundImageDark}')` }),
	}
})
</script>

<template>
	<div
		class="dashboard-section"
		:class="{
			'dashboard-section--wide': wide && !isSmallMobile,
			'dashboard-section--list': $slots.list,
			'dashboard-section--background': backgroundImage,
			'dashboard-section--mobile': isMobile,
			// On narrow screens the text sits closer to the busy part of the image
			'dashboard-section--veiled': $slots.list || isMobile,
		}"
		:style="backgroundStyle">
		<div class="dashboard-section__content">
			<h3 class="dashboard-section__title">
				{{ title }}
			</h3>
			<span v-if="subtitle" class="dashboard-section__subtitle">{{ subtitle }}</span>
			<span v-if="description" class="dashboard-section__description">{{ description }}</span>
			<slot name="list" />
			<div v-if="$slots.action" class="dashboard-section__action">
				<slot name="action" />
			</div>
		</div>
	</div>
</template>

<style lang="scss" scoped>
.dashboard-section {
	--dashboard-section-blur: 10px;
	// The dark themes below swap in the dark illustration, where the caller provides one
	--dashboard-section-image: var(--dashboard-section-image-light);
	display: flex;
	border-radius: var(--border-radius-large);
	overflow: hidden;
	border: 1px solid var(--color-primary-element-light-hover);
	height: 100%;

	&--wide {
		flex-direction: row;

		.dashboard-section__content {
			justify-content: center;
		}
	}

	&--background {
		position: relative;
		isolation: isolate;

		body[data-theme-dark] &,
		body[data-theme-dark-highcontrast] & {
			--dashboard-section-image: var(--dashboard-section-image-dark, var(--dashboard-section-image-light));
		}

		// System default theme following the OS preference
		@media (prefers-color-scheme: dark) {
			body[data-theme-default] & {
				--dashboard-section-image: var(--dashboard-section-image-dark, var(--dashboard-section-image-light));
			}
		}

		// The image sits in a pseudo-element rather than on the container itself, so that
		// the blur of the filled state below does not apply to the content on top.
		&::before {
			content: '';
			position: absolute;
			inset: 0;
			background-image: var(--dashboard-section-image);
			background-size: cover;
			background-position: right center;
			background-repeat: no-repeat;
			z-index: -1;
		}

		// Soften the image wherever content is rendered close to it: behind a list, or on
		// narrow screens. Empty states on wider screens keep the image sharp.
		&.dashboard-section--veiled {
			&::before {
				// Overscan, so the blur does not fade out towards the edges
				inset: calc(var(--dashboard-section-blur) * -1);
				filter: blur(var(--dashboard-section-blur));
			}

			&::after {
				content: '';
				position: absolute;
				inset: 0;
				background-color: var(--color-main-background);
				opacity: 0.5;
				z-index: -1;
			}
		}

		&.dashboard-section--wide .dashboard-section__content {
			// Keep the text clear of the illustration on the trailing side of the banner
			max-width: 65%;
		}
	}

	&__content {
		position: relative;
		display: flex;
		flex-direction: column;
		flex: auto;
		min-height: 0;
		// Without this the item cannot shrink below its content width, so a horizontally
		// scrollable list in the slot stretches the section instead of scrolling
		min-width: 0;
		padding: 0 calc(var(--default-grid-baseline) * 5) calc(var(--default-grid-baseline) * 2);
	}

	&--mobile &__content {
		padding-inline: calc(var(--default-grid-baseline) * 3);
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
