<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref } from 'vue'

import IconFileUpload from 'vue-material-design-icons/FileUpload.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

const loading = ref(false)
const listImport = ref(null)

const props = defineProps<{
	token: string,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const importedFile = ref<File>(null)

/**
 * Call native input[type='file'] to import a file
 */
function triggerImport() {
	listImport.value.value = ''
	listImport.value.click()
}

/**
 * Validate imported file and insert data into form fields
 * @param event import event
 */
function importList(event: Event) {
	const file = (event.target as HTMLInputElement).files?.[0]
	if (!file) {
		importedFile.value = null
	}

	importedFile.value = file
}
</script>

<template>
	<NcDialog class="import-list"
		:name="t('spreed', 'Import e-mail participants')"
		size="small"
		close-on-click-outside
		@update:open="emit('close')">
		<!--native file picker, hidden -->
		<input id="list-upload"
			ref="listImport"
			type="file"
			class="hidden-visually"
			@change="importList">

		<div class="import-list__wrapper">
			<NcTextField class="import-list__input"
				:model-value="importedFile?.name ?? ''"
				:placeholder="t('spreed', 'Import a file')"
				disabled />
			<NcButton class="import-list__button" @click="triggerImport">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<IconFileUpload v-else :size="20" />
				</template>
				{{ t('spreed', 'Browse …') }}
			</NcButton>
		</div>
	</NcDialog>
</template>

<style lang="scss" scoped>
.import-list {
	&__wrapper {
		display: flex;
		gap: var(--default-grid-baseline);
    margin-bottom: calc(var(--default-grid-baseline) * 3);
	}

	&__input {
		opacity: 1 !important;

		:deep(input) {
			opacity: 1 !important;
			color: var(--color-main-text) !important;
			border-color: var(--color-border-maxcontrast) !important;
		}
	}

	&__button {
		flex-shrink: 0;
	}
}
</style>
