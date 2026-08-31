<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'

const props = defineProps<{
	/** Index of the page currently shown in the stripe, starting at 0 */
	currentPage: number
	/** Number of pages the tiles of the stripe are spread over */
	numberOfPages: number
	/** Whether there is a page before the current one */
	hasPreviousPage: boolean
	/** Whether there is a page after the current one */
	hasNextPage: boolean
	/** Whether the stripe is expanded */
	isOpen: boolean
}>()

const emit = defineEmits<{
	previous: []
	next: []
	toggle: []
}>()

// A collapsed stripe shows no tile, so it is never paginated
const isPaginated = computed(() => props.isOpen && props.numberOfPages > 1)

const pageIndicator = computed(() => `${props.currentPage + 1}/${props.numberOfPages}`)

const pageLabel = computed(() => t('spreed', 'Page {page} of {pages}', {
	page: props.currentPage + 1,
	pages: props.numberOfPages,
}))

const toggleLabel = computed(() => props.isOpen
	? t('spreed', 'Collapse participant bar')
	: t('spreed', 'Expand participant bar'))
</script>

<template>
	<div class="stripe-controls">
		<!-- Paging through the tiles, which the stripe only does when it holds
			more of them than it can show -->
		<div v-if="isPaginated" class="stripe-controls__group">
			<div class="stripe-controls__slot" :class="{ 'stripe-controls__slot--shown': hasPreviousPage }">
				<NcButton
					variant="tertiary"
					size="small"
					:disabled="!hasPreviousPage"
					:aria-label="t('spreed', 'Previous page of videos')"
					:title="t('spreed', 'Previous page of videos')"
					@click="emit('previous')">
					<template #icon>
						<IconChevronLeft
							class="bidirectional-icon"
							:size="20" />
					</template>
				</NcButton>
			</div>

			<div class="stripe-controls__slot">
				<span
					class="stripe-controls__page"
					role="status"
					:aria-label="pageLabel">
					{{ pageIndicator }}
				</span>
			</div>

			<div class="stripe-controls__slot" :class="{ 'stripe-controls__slot--shown': hasNextPage }">
				<NcButton
					variant="tertiary"
					size="small"
					:disabled="!hasNextPage"
					:aria-label="t('spreed', 'Next page of videos')"
					:title="t('spreed', 'Next page of videos')"
					@click="emit('next')">
					<template #icon>
						<IconChevronRight
							class="bidirectional-icon"
							:size="20" />
					</template>
				</NcButton>
			</div>
		</div>

		<!-- Collapsing and expanding the stripe itself -->
		<div class="stripe-controls__group">
			<NcButton
				variant="tertiary"
				size="small"
				:aria-label="toggleLabel"
				:title="toggleLabel"
				@click="emit('toggle')">
				<template #icon>
					<IconChevronDown
						v-if="isOpen"
						:size="20" />
					<IconChevronUp
						v-else
						:size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<style lang="scss" scoped>
// The controls are painted with the palette of the stripe they belong to, which
// is the dark one the call view is painted with whichever theme is set
.stripe-controls {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	width: fit-content;
	padding-block-start: var(--default-grid-baseline);
	padding-inline-end: var(--default-grid-baseline);
	// Barely there while the call is not being looked at, as they sit over the
	// tiles, and in their own color as soon as it is
	opacity: .4;
	transition: opacity var(--animation-quick) ease-in-out;

	@media (hover: none) {
		opacity: 1;
	}

	#call-container:hover &,
	&:focus-within {
		opacity: 1;
	}
}

// Paging through the tiles and collapsing the stripe are two controls, each on
// a surface of its own
.stripe-controls__group {
	display: flex;
	align-items: center;
	// The surface the bottom bar puts its own buttons over the call
	border-radius: var(--border-radius-pill, calc(var(--default-clickable-area) / 2));
	background-color: var(--color-primary-light);
}

// Every control of a group takes the room it needs once the controls are
// hovered, and only the ones worth showing on their own take any before that
.stripe-controls__slot {
	display: grid;
	// A collapsed track is only as narrow as what it holds unless its minimum is
	// given, and a button does not shrink below its clickable area, so the room
	// it keeps is taken out of the flow by the slot itself
	grid-template-columns: minmax(0, 0fr);
	overflow: hidden;
	opacity: 0;
	transition: grid-template-columns var(--animation-quick) ease-in-out,
		opacity var(--animation-quick) ease-in-out;

	&--shown {
		grid-template-columns: minmax(0, 1fr);
		opacity: 1;
	}

	.stripe-controls:hover &,
	.stripe-controls:focus-within & {
		grid-template-columns: minmax(0, 1fr);
		opacity: 1;
	}
}

.stripe-controls__page {
	display: block;
	padding-inline: var(--default-grid-baseline);
	color: var(--color-main-text);
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

@media (prefers-reduced-motion: reduce) {
	.stripe-controls,
	.stripe-controls__slot {
		transition: none;
	}
}
</style>
