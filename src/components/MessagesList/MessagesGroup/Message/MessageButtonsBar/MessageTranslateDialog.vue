<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal ref="translate-modal"
		size="large"
		:container="container"
		@close="$emit('close')">
		<div v-if="isMounted" class="translate-dialog">
			<h2> {{ t('spreed', 'Translate message') }} </h2>
			<div class="translate-dialog__wrapper">
				<NcSelect v-model="selectedFrom"
					class="translate-dialog__select"
					input-id="from"
					:aria-label-combobox="t('spreed', 'Source language to translate from')"
					:placeholder="t('spreed', 'Translate from')"
					:options="optionsFrom"
					no-wrap />

				<ArrowRight />

				<NcSelect v-model="selectedTo"
					class="translate-dialog__select"
					input-id="to"
					:aria-label-combobox="t('spreed', 'Target language to translate into')"
					:placeholder="t('spreed', 'Translate to')"
					:options="optionsTo"
					no-wrap />

				<NcButton type="primary"
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

			<template v-if="translatedMessage">
				<NcRichText class="translate-dialog__message translate-dialog__message-translation"
					:text="translatedMessage"
					:arguments="richParameters"
					:reference-limit="0" />
				<NcButton class="translate-dialog__copy-button"
					@click="handleCopyTranslation">
					<template #icon>
						<ContentCopy />
					</template>
					{{ t('spreed', 'Copy translated text') }}
				</NcButton>
			</template>
		</div>
	</NcModal>
</template>

<script>
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'

// eslint-disable-next-line
// import { showError, showSuccess } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import { getTranslationLanguages, translateText } from '../../../../../services/translationService.js'

export default {
	name: 'MessageTranslateDialog',

	components: {
		ArrowRight,
		ContentCopy,
		NcRichText,
		NcModal,
		NcSelect,
		NcButton,
		NcLoadingIcon,
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
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		userLanguage() {
			return navigator.language.substring(0, 2)
		},

		sourceTree() {
			const tree = {}
			const uniqueSourceLanguages = Array.from(new Set(this.availableLanguages?.map(element => element.from)))

			uniqueSourceLanguages.forEach(language => {
				tree[language] = {
					id: language,
					label: this.availableLanguages?.find(element => element.from === language)?.fromLabel,
					translations: this.availableLanguages?.filter(element => element.from === language).map(model => ({
						id: model.to,
						label: model.toLabel,
					})),
				}
			})

			return tree
		},

		translationTree() {
			const tree = {}
			const uniqueTranslateLanguages = Array.from(new Set(this.availableLanguages?.map(element => element.to)))

			uniqueTranslateLanguages.forEach(language => {
				tree[language] = {
					id: language,
					label: this.availableLanguages?.find(element => element.to === language)?.toLabel,
					sources: this.availableLanguages?.filter(element => element.to === language).map(model => ({
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
				: Object.values(this.sourceTree).map(model => ({
					id: model.id,
					label: model.label,
				}))
		},

		optionsTo() {
			return this.selectedFrom?.id
				? this.sourceTree[this.selectedFrom?.id]?.translations
				: Object.values(this.translationTree).map(model => ({
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
		this.selectedTo = this.optionsTo.find(language => language.id === this.userLanguage) || null

		if (this.selectedTo) {
			this.translateMessage()
		}

		this.$nextTick(() => {
			// FIXME trick to avoid focusTrap() from activating on NcSelect
			this.isMounted = !!this.$refs['translate-modal'].randId
		})
	},

	methods: {
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
	position: relative;
	display: flex;
	flex-direction: column;
	min-height: 400px;
	padding: calc(var(--default-grid-baseline) * 3);
	background-color: var(--color-main-background);

	&__wrapper {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 4);
		padding: calc(var(--default-grid-baseline) * 2);
	}

	& &__select {
		width: 50%;
	}

	&__button {
		flex-shrink: 0;
		margin-left: auto;
	}

	&__message {
		padding: calc(var(--default-grid-baseline) * 2);
		flex-grow: 1;
		border-radius: var(--border-radius-large);

		&-source {
			color: var(--color-text-maxcontrast);
			margin-bottom: calc(var(--default-grid-baseline) * 2);
			border: 2px solid var(--color-border);
		}

		&-translation {
			border: 2px solid var(--color-primary-element);
		}
	}

	& &__copy-button {
		margin-top: calc(var(--default-grid-baseline) * 2);
		align-self: end;
	}
}
</style>
