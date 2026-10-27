<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Sticker } from '../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconStickerEmoji from 'vue-material-design-icons/StickerEmoji.vue'
import { useStickersStore } from '../../stores/stickers.ts'
import { generatePreviewUrl } from '../../utils/davUtils.ts'

const { folder, disabled = false } = defineProps<{
	/** Path of the folder with sticker packs, for the hint when there are none */
	folder: string
	disabled?: boolean
}>()

const emit = defineEmits<{
	select: [sticker: Sticker]
}>()

const stickersStore = useStickersStore()

const shown = ref(false)
const selectedPackId = ref<number | null>(null)

const selectedPack = computed(() => stickersStore.packs.find((pack) => pack.id === selectedPackId.value)
	?? stickersStore.packs[0])

watch(shown, (value) => {
	if (value) {
		stickersStore.fetchPacks()
	}
})

/**
 * Preview of a sticker, refreshed when its file changes
 *
 * @param sticker The sticker
 */
function previewUrl(sticker: Sticker): string {
	return generatePreviewUrl(sticker.id, { x: 96, y: 96 }) + '&c=' + encodeURIComponent(sticker.etag)
}

/**
 * Send the sticker and close the picker
 *
 * @param sticker The sticker
 */
function selectSticker(sticker: Sticker) {
	emit('select', sticker)
	shown.value = false
}
</script>

<template>
	<NcPopover v-model:shown="shown" popupRole="dialog">
		<template #trigger>
			<NcButton
				:disabled="disabled"
				variant="tertiary"
				:title="t('spreed', 'Send a sticker')"
				:aria-label="t('spreed', 'Send a sticker')"
				:aria-haspopup="true">
				<template #icon>
					<IconStickerEmoji :size="20" />
				</template>
			</NcButton>
		</template>

		<div class="sticker-picker">
			<NcLoadingIcon v-if="!stickersStore.loaded" class="sticker-picker__loading" :size="44" />

			<NcEmptyContent
				v-else-if="!selectedPack"
				:name="t('spreed', 'No stickers')"
				:description="t('spreed', 'Add images to subfolders of the folder \'{folder}\' to send them as stickers', { folder })">
				<template #icon>
					<IconStickerEmoji />
				</template>
			</NcEmptyContent>

			<template v-else>
				<div class="sticker-picker__packs" role="tablist" :aria-label="t('spreed', 'Sticker packs')">
					<NcButton
						v-for="pack in stickersStore.packs"
						:key="pack.id"
						role="tab"
						size="small"
						:aria-selected="pack.id === selectedPack.id"
						:variant="pack.id === selectedPack.id ? 'secondary' : 'tertiary'"
						@click="selectedPackId = pack.id">
						{{ pack.name }}
					</NcButton>
				</div>

				<ul class="sticker-picker__stickers" role="tabpanel" :aria-label="selectedPack.name">
					<li v-for="sticker in selectedPack.stickers" :key="sticker.id">
						<button
							type="button"
							class="sticker-picker__sticker"
							:title="sticker.name"
							:aria-label="sticker.name"
							@click="selectSticker(sticker)">
							<img :src="previewUrl(sticker)" alt="" loading="lazy">
						</button>
					</li>
				</ul>
			</template>
		</div>
	</NcPopover>
</template>

<style lang="scss" scoped>
.sticker-picker {
	width: min(calc(80 * var(--default-grid-baseline)), 90vw);
	max-height: min(calc(100 * var(--default-grid-baseline)), 60vh);
	display: flex;
	flex-direction: column;
	padding: calc(2 * var(--default-grid-baseline));

	&__loading {
		margin: calc(4 * var(--default-grid-baseline)) auto;
	}

	&__packs {
		display: flex;
		gap: var(--default-grid-baseline);
		overflow-x: auto;
		flex-shrink: 0;
		padding-block-end: calc(2 * var(--default-grid-baseline));
	}

	&__stickers {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(calc(16 * var(--default-grid-baseline)), 1fr));
		gap: var(--default-grid-baseline);
		overflow-y: auto;
	}

	&__sticker {
		width: 100%;
		aspect-ratio: 1;
		margin: 0;
		padding: var(--default-grid-baseline);
		border: none;
		border-radius: var(--border-radius-element);
		background: transparent;
		cursor: pointer;

		&:hover,
		&:focus-visible {
			background-color: var(--color-background-hover);
		}

		img {
			width: 100%;
			height: 100%;
			object-fit: contain;
		}
	}
}
</style>
