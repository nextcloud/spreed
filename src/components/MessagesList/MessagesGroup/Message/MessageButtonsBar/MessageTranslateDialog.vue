<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		class="translate-dialog"
		:name="t('spreed', 'Translate message')"
		size="large"
		closeOnClickOutside
		@update:open="$emit('close')">
		<template v-if="isMounted" #default>
			<div class="translate-dialog__wrapper">
				<NcSelect
					v-model="selectedFrom"
					class="translate-dialog__select"
					inputId="from"
					:disabled="isLoading"
					:clearable="false"
					:aria-label-combobox="t('spreed', 'Source language to translate from')"
					:placeholder="t('spreed', 'Translate from')"
					:options="optionsFrom"
					noWrap />

				<IconArrowRight class="bidirectional-icon" />

				<NcSelect
					v-model="selectedTo"
					class="translate-dialog__select"
					inputId="to"
					:disabled="isLoading"
					:clearable="false"
					:aria-label-combobox="t('spreed', 'Target language to translate into')"
					:placeholder="t('spreed', 'Translate to')"
					:options="optionsTo"
					noWrap />

				<NcAssistantButton
					variant="primary"
					:disabled="disabled"
					class="translate-dialog__button"
					@click="handleTranslate">
					{{ isTranslating ? t('spreed', 'Translating') : t('spreed', 'Translate') }}
				</NcAssistantButton>
			</div>

			<NcRichText
				class="translate-dialog__message translate-dialog__message-source"
				:text="message"
				:arguments="richParameters"
				:referenceLimit="0" />

			<NcRichText
				v-if="translatedMessage"
				class="translate-dialog__message translate-dialog__message-translation"
				:text="translatedMessage"
				:arguments="richParameters"
				:referenceLimit="0" />

			<div v-if="translatedMessage" class="translate-dialog__ai-note">
				{{ t('spreed', 'This translation is AI generated and may contain mistakes.') }}
			</div>
		</template>

		<template v-if="translatedMessage" #actions>
			<NcButton @click="handleCopyTranslation">
				<template #icon>
					<IconContentCopy />
				</template>
				{{ t('spreed', 'Copy translated text') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcAssistantButton from '@nextcloud/vue/components/NcAssistantButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import IconArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import { TASK_PROCESSING } from '../../../../../constants.ts'
import { deleteTaskById, getTaskById, getTaskTypes } from '../../../../../services/coreService.ts'
import { scheduleTranslateTask } from '../../../../../services/translationService.ts'

const POLLING_INTERVAL = 2000

export default {
	name: 'MessageTranslateDialog',

	components: {
		NcAssistantButton,
		NcButton,
		NcDialog,
		NcRichText,
		NcSelect,
		// Icons
		IconArrowRight,
		IconContentCopy,
	},

	props: {
		message: {
			type: String,
			required: true,
		},

		richParameters: {
			type: Object,
			required: true,
		},
	},

	emits: ['close'],

	data() {
		return {
			isMounted: false,
			translateTaskType: null,
			selectedFrom: null,
			selectedTo: null,
			isLoading: false,
			isTranslating: false,
			translatedMessage: '',
			taskId: null,
			pollingTimeout: null,
			// Incremented whenever a pending translation is discarded, so
			// responses of outdated requests can be ignored
			requestId: 0,
		}
	},

	computed: {
		userLanguage() {
			return navigator.language.substring(0, 2)
		},

		optionsFrom() {
			return this.mapLanguageOptions(this.translateTaskType?.inputShapeEnumValues?.origin_language)
		},

		optionsTo() {
			return this.mapLanguageOptions(this.translateTaskType?.inputShapeEnumValues?.target_language)
		},

		disabled() {
			return this.isLoading || this.isTranslating
				|| this.selectedFrom === null || this.selectedTo === null
		},
	},

	watch: {
		selectedTo() {
			this.resetTranslation()
		},

		selectedFrom() {
			this.resetTranslation()
		},
	},

	async mounted() {
		this.$nextTick(() => {
			// FIXME trick to avoid focusTrap() from activating on NcSelect
			// REMOVE: if we add a fix to disable initial focus in NcModal upstream
			this.isMounted = true
		})

		try {
			this.isLoading = true
			const response = await getTaskTypes()
			this.translateTaskType = response.data.ocs.data.types[TASK_PROCESSING.TYPE.TRANSLATE] ?? null
		} catch (error) {
			console.error('Error while trying to get translation languages', error)
			this.translateTaskType = null
		} finally {
			this.isLoading = false
		}

		const defaultFrom = this.translateTaskType?.inputShapeDefaults?.origin_language
		this.selectedFrom = this.optionsFrom.find((language) => language.id === defaultFrom) ?? null

		this.selectedTo = this.optionsTo.find((language) => language.id === this.userLanguage) ?? null

		// Wait for the watchers of the initial selection to be handled
		await this.$nextTick()

		if (this.selectedFrom && this.selectedTo) {
			this.translateMessage()
		}
	},

	beforeUnmount() {
		this.discardTranslation()
	},

	methods: {
		t,

		mapLanguageOptions(enumValues) {
			return enumValues?.map((enumValue) => ({ id: enumValue.value, label: enumValue.name })) ?? []
		},

		handleTranslate() {
			this.translateMessage()
		},

		async translateMessage() {
			this.discardTranslation()
			const requestId = this.requestId
			this.isTranslating = true

			try {
				const response = await scheduleTranslateTask(this.message, this.selectedFrom.id, this.selectedTo.id)
				const task = response.data.ocs.data.task

				if (this.requestId !== requestId) {
					// The translation was discarded in the meantime
					this.deleteTask(task.id)
					return
				}

				this.taskId = task.id
				this.handleTask(task)
			} catch (error) {
				if (this.requestId === requestId) {
					this.handleTranslationError(error)
				}
			}
		},

		async pollTask() {
			const requestId = this.requestId

			try {
				const response = await getTaskById(this.taskId)
				if (this.requestId === requestId) {
					this.handleTask(response.data.ocs.data.task)
				}
			} catch (error) {
				if (this.requestId === requestId) {
					this.handleTranslationError(error)
				}
			}
		},

		handleTask(task) {
			switch (task.status) {
				case TASK_PROCESSING.STATUS.SUCCESSFUL: {
					this.translatedMessage = task.output?.output ?? ''
					this.isTranslating = false
					break
				}
				case TASK_PROCESSING.STATUS.FAILED:
				case TASK_PROCESSING.STATUS.CANCELLED:
				case TASK_PROCESSING.STATUS.UNKNOWN: {
					this.taskId = null
					this.isTranslating = false
					showError(t('spreed', 'The message could not be translated'))
					break
				}
				case TASK_PROCESSING.STATUS.SCHEDULED:
				case TASK_PROCESSING.STATUS.RUNNING:
				default: {
					// Task is still processing, scheduling next request
					this.pollingTimeout = setTimeout(this.pollTask, POLLING_INTERVAL)
					break
				}
			}
		},

		handleTranslationError(error) {
			console.error('Error while trying to translate the message', error)
			this.taskId = null
			this.isTranslating = false
			showError(error.response?.data?.ocs?.data?.message ?? t('spreed', 'The message could not be translated'))
		},

		resetTranslation() {
			this.discardTranslation()
			this.translatedMessage = ''
		},

		/**
		 * Stops polling and discards a translation task that is still pending
		 */
		discardTranslation() {
			this.requestId++

			if (this.pollingTimeout) {
				clearTimeout(this.pollingTimeout)
				this.pollingTimeout = null
			}

			if (this.isTranslating && this.taskId !== null) {
				this.deleteTask(this.taskId)
			}

			this.taskId = null
			this.isTranslating = false
		},

		deleteTask(taskId) {
			deleteTaskById(taskId).catch((error) => {
				console.error('Error while trying to delete the translation task', error)
			})
		},

		async handleCopyTranslation() {
			try {
				await navigator.clipboard.writeText(this.translatedMessage)
				showSuccess(t('spreed', 'Translation copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Translation could not be copied'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.translate-dialog {
	:deep(.dialog__content) {
		position: relative;
		display: flex;
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) * 2);
		min-height: 300px;
		padding-bottom: calc(var(--default-grid-baseline) * 3);
	}

	&__wrapper {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 4);
	}

	& &__select {
		width: 50%;
	}

	&__button {
		flex-shrink: 0;
		margin-inline-start: auto;
	}

	&__message {
		padding: calc(var(--default-grid-baseline) * 2);
		flex-grow: 1;
		border-radius: var(--border-radius-large);

		&-source {
			color: var(--color-text-maxcontrast);
			border: 2px solid var(--color-border);
		}

		&-translation {
			border: 2px solid var(--color-primary-element);
		}
	}

	&__ai-note {
		color: var(--color-text-maxcontrast);
	}
}
</style>
