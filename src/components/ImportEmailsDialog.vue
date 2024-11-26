<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref } from 'vue'

import IconFileUpload from 'vue-material-design-icons/FileUpload.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t, n } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { importEmails } from '../services/participantsService.js'

const loading = ref(false)
const listImport = ref(null)

const props = defineProps<{
	token: string,
	container?: string,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const importedFile = ref<File>(null)
const uploadResult = ref(null)
const uploadResultCaption = computed(() => {
	return uploadResult.value?.error
		? { class: 'import-list__caption--error', label: t('spreed', 'Error while verifying uploaded file') }
		: { class: 'import-list__caption--success', label: t('spreed', 'Uploaded file is verified') }
})

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
	importedFile.value = file ?? null

	testList(importedFile.value)
}

/**
 * Verify imported file and show results
 * @param file file to upload
 */
async function testList(file: File) {
	loading.value = true
	uploadResult.value = null
	try {
		const response = await importEmails(props.token, file, true)
		uploadResult.value = response.data.ocs.data
	} catch (error) {
		uploadResult.value = error?.response?.data?.ocs?.data
	} finally {
		loading.value = false
	}
}

/**
 * Verify imported file and add participants
 * @param file file to upload
 */
async function submitList(file: File) {
	if (!file) {
		return
	}

	try {
		await importEmails(props.token, file, false)
		showSuccess(t('spreed', 'Participants added successfully'))
		emit('close')
	} catch (e) {
		showError(t('spreed', 'Error while adding participants'))
		console.error(e)
	}
}
</script>

<template>
	<NcDialog class="import-list"
		:name="t('spreed', 'Import e-mail participants')"
		size="small"
		close-on-click-outside
		:container="container"
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

		<div class="import-list__form">
			<template v-if="loading">
				<p class="import-list__caption">
					{{ t('spreed', 'Verifying uploaded file …') }}
				</p>
				<p class="import-list__description">
					{{ t('spreed', 'This might take a moment') }}
				</p>
			</template>
			<template v-else-if="uploadResult">
				<p class="import-list__caption"
					:class="[uploadResultCaption.class]">
					{{ uploadResultCaption.label }}
				</p>
				<p v-if="uploadResult?.invalid" class="import-list__description">
					{{ n('spreed', '%n invalid e-mail', '%n invalid e-mails', uploadResult.invalid) }}
				</p>
				<p v-if="uploadResult?.message" class="import-list__description">
					{{ uploadResult.message }}
				</p>
				<p v-if="uploadResult?.duplicates" class="import-list__description">
					{{ n('spreed', '%n e-mail is already imported or a duplicate', '%n e-mails are already imported or duplicates', uploadResult.duplicates) }}
				</p>
				<p v-if="uploadResult?.invites" class="import-list__description import-list__description--separated">
					{{ n('spreed', '%n invitation can be sent', '%n invitations can be sent', uploadResult.invites) }}
				</p>
			</template>
		</div>

		<template #actions>
			<NcButton type="primary"
				:disabled="!uploadResult"
				@click="submitList(importedFile)">
				{{ t('spreed', 'Send invitations') }}
			</NcButton>
		</template>
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

	&__form {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
	}

  &__caption {
    font-weight: bold;
    &--error {
      color: var(--color-error);
    }
    &--success {
      color: var(--color-success);
    }
  }

  &__description {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    &--separated {
      border-top: 1px solid var(--color-border-dark);
    }
  }
}
</style>
