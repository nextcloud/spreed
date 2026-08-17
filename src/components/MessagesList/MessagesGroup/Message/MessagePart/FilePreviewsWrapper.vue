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

const fileKeys = computed(() => getFilePreviewKeys(props.message))

/**
 * Get referenceId of a file parameter to look up a local preview (if available).
 * Client-only workaround for combined file messages
 *
 * @param key key of the file parameter ('file', 'file-1', …)
 */
function getReferenceId(key: string): string {
	// @ts-expect-error: 'referenceId' does not exist in type RichObjectParameter
	return props.message.messageParameters[key].referenceId ?? props.message.referenceId
}
</script>

<template>
	<div
		class="file-previews-wrapper"
		:class="{ 'file-previews-wrapper--combined': fileKeys.length > 1 }">
		<FilePreview
			v-for="key in fileKeys"
			:key="key"
			:token="message.token"
			:messageId="message.id"
			:itemType="getItemTypeFromMessage(message, key)"
			:referenceId="getReferenceId(key)"
			:file="message.messageParameters[key]" />
	</div>
</template>

<style lang="scss" scoped>
.file-previews-wrapper {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	gap: var(--default-grid-baseline);
}

.file-previews-wrapper--combined {
	:deep(.file-preview) {
		--preview-size: 80px;
		--preview-name-height: 24px;
		flex-shrink: 0;
		width: var(--preview-size);
		height: calc(var(--preview-size) + var(--preview-name-height));

		.image-container {
			width: var(--preview-size) !important;
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
			font-weight: normal;
			font-size: var(--font-size-small);
		}
	}
}
</style>
