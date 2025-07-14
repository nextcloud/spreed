<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog ref="translateDialog"
		class="translate-dialog"
		:name="t('spreed', 'Translate message')"
		size="large"
		close-on-click-outside
		@update:open="$emit('close')">
		<template v-if="isMounted" #default>
			<div class="translate-dialog__wrapper">
				<NcSelect v-model="selectedFrom"
					class="translate-dialog__select"
					input-id="from"
					:aria-label-combobox="t('spreed', 'Source language to translate from')"
					:placeholder="t('spreed', 'Translate from')"
					:options="optionsFrom"
					no-wrap />

				<IconArrowRight class="bidirectional-icon" />

				<NcSelect v-model="selectedTo"
					class="translate-dialog__select"
					input-id="to"
					:aria-label-combobox="t('spreed', 'Target language to translate into')"
					:placeholder="t('spreed', 'Translate to')"
					:options="optionsTo"
					no-wrap />

				<NcButton variant="primary"
					:disabled="isLoading"
					class="translate-dialog__button"
					@click="handleTranslate">
					<template v-if="isLoading" #icon>
						<NcLoadingIcon />
					</template>
					{{ isLoading ? t('spreed', 'Translating') : t('spreed', 'Translate') }}
				</NcButton>
			</div>

			<NcRichText class="translate-dialog__message translate-dialog__message-source"
				:text="message"
				:arguments="richParameters"
				:reference-limit="0" />

			<NcRichText v-if="translatedMessage"
				class="translate-dialog__message translate-dialog__message-translation"
				:text="translatedMessage"
				:arguments="richParameters"
				:reference-limit="0" />
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
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import IconArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import { getTranslationLanguages, translateText } from '../../../../../services/translationService.js'

export default {
	name: 'MessageTranslateDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
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
			availableLanguages: null,
			selectedFrom: null,
			selectedTo: null,
			isLoading: false,
			translatedMessage: '',
		}
	},

	computed: {
		userLanguage() {
			return navigator.language.substring(0, 2)
		},

		sourceTree() {
			const tree = {}
			const uniqueSourceLanguages = Array.from(new Set(this.availableLanguages?.map((element) => element.from)))

			uniqueSourceLanguages.forEach((language) => {
				tree[language] = {
					id: language,
					label: this.availableLanguages?.find((element) => element.from === language)?.fromLabel,
					translations: this.availableLanguages?.filter((element) => element.from === language).map((model) => ({
						id: model.to,
						label: model.toLabel,
					})),
				}
			})

			return tree
		},

		translationTree() {
			const tree = {}
			const uniqueTranslateLanguages = Array.from(new Set(this.availableLanguages?.map((element) => element.to)))

			uniqueTranslateLanguages.forEach((language) => {
				tree[language] = {
					id: language,
					label: this.availableLanguages?.find((element) => element.to === language)?.toLabel,
					sources: this.availableLanguages?.filter((element) => element.to === language).map((model) => ({
						id: model.from,
						label: model.fromLabel,
					})),
				}
			})

			return tree
		},

		optionsFrom() {
			return this.selectedTo?.id
				? this.translationTree[this.selectedTo?.id]?.sources
				: Object.values(this.sourceTree).map((model) => ({
						id: model.id,
						label: model.label,
					}))
		},

		optionsTo() {
			return this.selectedFrom?.id
				? this.sourceTree[this.selectedFrom?.id]?.translations
				: Object.values(this.translationTree).map((model) => ({
						id: model.id,
						label: model.label,
					}))
		},
	},

	watch: {
		selectedTo() {
			this.translatedMessage = ''
		},

		selectedFrom() {
			this.translatedMessage = ''
		},
	},

	async created() {
		const response = await getTranslationLanguages()
		this.availableLanguages = response.data.ocs.data.languages
	},

	mounted() {
		this.selectedTo = this.optionsTo.find((language) => language.id === this.userLanguage) || null

		if (this.selectedTo) {
			this.translateMessage()
		}

		this.$nextTick(() => {
			// FIXME trick to avoid focusTrap() from activating on NcSelect
			this.isMounted = !!this.$refs.translateDialog.navigationId
		})
	},

	methods: {
		t,
		handleTranslate() {
			this.translateMessage(this.selectedFrom?.id)
		},

		async translateMessage(sourceLanguage = null) {
			try {
				this.isLoading = true
				const response = await translateText(this.message, sourceLanguage, this.selectedTo?.id)
				this.translatedMessage = response.data.ocs.data.text
			} catch (error) {
				console.error(error)
				showError(error.response?.data?.ocs?.data?.message ?? t('spreed', 'The message could not be translated'))
			} finally {
				this.isLoading = false
			}
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
}
</style>
