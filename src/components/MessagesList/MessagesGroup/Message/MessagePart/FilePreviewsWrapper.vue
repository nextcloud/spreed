<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { ChatMessage } from '../../../../../types/index.ts'

import { computed } from 'vue'
import FilePreview from './FilePreview.vue'
import { getItemTypeFromMessage } from '../../../../../utils/getItemTypeFromMessage.ts'
import { getFilePreviewKeys } from '../../../../../utils/message.ts'

const props = defineProps<{
	message: ChatMessage
}>()

// Image tiles shown before the rest collapse into a "+X more" tile
const MAX_VISIBLE_IMAGES = 4

const fileKeys = computed(() => getFilePreviewKeys(props.message))

/**
 * Whether a file parameter is an image or video with a server-side preview (grouped, no name shown)
 *
 * @param key key of the file parameter ('file', 'file-1', …)
 */
function isImageKey(key: string): boolean {
	const file = props.message.messageParameters[key]
	const isMedia = file.mimetype?.startsWith('image/') || file.mimetype?.startsWith('video/') || false
	// @ts-expect-error: 'localUrl' does not exist in type RichObjectParameter (temporary upload)
	return isMedia && (file['preview-available'] === 'yes' || !!file.localUrl)
}

const imageKeys = computed(() => fileKeys.value.filter(isImageKey))
const otherKeys = computed(() => fileKeys.value.filter((key) => !isImageKey(key)))

// Multiple media tiles shrink into a grid; a single one keeps its full size
const isImageRowCombined = computed(() => imageKeys.value.length > 1)

const hiddenImageCount = computed(() => {
	return imageKeys.value.length > MAX_VISIBLE_IMAGES
		? imageKeys.value.length - (MAX_VISIBLE_IMAGES - 1)
		: 0
})

// Last tile doubles as the "+X more" tile (dimmed via CSS) when some images are hidden
const visibleImageKeys = computed(() => imageKeys.value.slice(0, MAX_VISIBLE_IMAGES))

// Quoted, for the `content` CSS property via v-bind below
const moreCountLabel = computed(() => `"+${hiddenImageCount.value}"`)

/**
 * referenceId of a file parameter, to look up a local preview (client-only workaround)
 *
 * @param key key of the file parameter ('file', 'file-1', …)
 */
function getReferenceId(key: string): string {
	// @ts-expect-error: 'referenceId' does not exist in type RichObjectParameter
	return props.message.messageParameters[key].referenceId ?? props.message.referenceId
}
</script>

<template>
	<div class="file-previews-wrapper">
		<div
			v-if="imageKeys.length"
			class="file-previews-wrapper__image-row"
			:class="{
				'file-previews-wrapper__image-row--has-more': hiddenImageCount > 0,
				'file-previews-wrapper__image-row--combined': isImageRowCombined,
			}">
			<FilePreview
				v-for="key in visibleImageKeys"
				:key="key"
				:token="message.token"
				:messageId="message.id"
				:itemType="getItemTypeFromMessage(message, key)"
				:referenceId="getReferenceId(key)"
				:file="message.messageParameters[key]" />
		</div>
		<div
			v-if="otherKeys.length"
			class="file-previews-wrapper__other-column">
			<FilePreview
				v-for="key in otherKeys"
				:key="key"
				rowLayout
				:token="message.token"
				:messageId="message.id"
				:itemType="getItemTypeFromMessage(message, key)"
				:referenceId="getReferenceId(key)"
				:file="message.messageParameters[key]" />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.file-previews-wrapper {
	display: flex;
	flex-direction: column;
	align-items: stretch;
	width: 100%;
	min-width: 0;
	gap: var(--default-grid-baseline);

	&__image-row {
		display: flex;
		align-items: flex-start;
		gap: var(--default-grid-baseline);
	}

	&__other-column {
		display: flex;
		flex-direction: column;
		align-items: stretch;
		gap: calc(var(--default-grid-baseline) / 2);
	}

	// Dim the last tile with a "+X more" overlay
	&__image-row--has-more :deep(.file-preview:last-child) {
		position: relative;

		&::after {
			content: v-bind(moreCountLabel);
			position: absolute;
			inset: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: var(--border-radius);
			background-color: rgba(0, 0, 0, 0.5);
			color: #ffffff;
			font-size: var(--font-size-large);
			font-weight: bold;
			pointer-events: none;
		}
	}
}

.file-previews-wrapper__image-row--combined {
	:deep(.file-preview) {
		--preview-size: 80px;
		--preview-name-height: 24px;
		flex: 1 1 var(--preview-size);
		width: var(--preview-size);
		min-width: 0;
		max-width: var(--preview-size);
		height: var(--preview-size);
		line-height: 0;

		// Reserve space for the name, shown only when a preview fails to load
		&:has(.name-container) {
			height: calc(var(--preview-size) + var(--preview-name-height));
			line-height: normal;
		}

		.image-container {
			width: 100% !important;
			height: var(--preview-size) !important;
		}

		.file-preview__image {
			width: 100%;
			height: 100%;
			min-height: unset;
			max-height: none;
			border-radius: var(--border-radius);

			&.mimeicon {
				object-fit: contain;
			}
		}

		.name-container {
			height: var(--preview-name-height);
			line-height: normal;
			font-size: var(--font-size-small);
		}
	}
}
</style>
