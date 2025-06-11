<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('spreed', 'Create and share a new file')"
		size="large"
		class="templates-picker"
		close-on-click-outside
		@update:open="closeModal">
		<form class="templates-picker__form"
			:style="style"
			@submit.prevent="handleCreateNewFile">
			<NcTextField id="new-file-form-name"
				ref="textField"
				v-model="newFileTitle"
				:error="!!newFileError"
				:helper-text="newFileError"
				:label="t('spreed', 'Name of the new file')"
				:placeholder="newFileTitle" />

			<ul v-if="templates.length > 1" class="templates-picker__list">
				<NewMessageTemplatePreview v-for="template in templates"
					:key="template.fileid"
					:basename="template.basename"
					:checked="checked === template.fileid"
					:fileid="template.fileid"
					:filename="template.filename"
					:preview-url="template.previewUrl"
					:has-preview="template.hasPreview"
					:mime="template.mime"
					:ratio="fileTemplate.ratio"
					@check="onCheck" />
			</ul>
		</form>

		<template #actions>
			<NcButton variant="primary"
				:disabled="loading"
				@click="handleCreateNewFile">
				<template v-if="loading" #icon>
					<NcLoadingIcon />
				</template>
				{{ t('spreed', 'Create file') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NewMessageTemplatePreview from './NewMessageTemplatePreview.vue'
import { useViewer } from '../../composables/useViewer.js'
import { createNewFile, shareFile } from '../../services/filesSharingServices.ts'

export default {
	name: 'NewMessageNewFileDialog',

	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcTextField,
		NewMessageTemplatePreview,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		showNewFileDialog: {
			type: Number,
			required: true,
		},
	},

	emits: ['dismiss'],

	setup() {
		const { openViewer } = useViewer('files')
		return { openViewer }
	},

	data() {
		return {
			newFileTitle: t('spreed', 'New file'),
			newFileError: '',
			checked: -1,
			loading: false,
		}
	},

	computed: {
		fileTemplateOptions() {
			return this.$store.getters.fileTemplates
		},

		fileTemplate() {
			return this.fileTemplateOptions[this.showNewFileDialog]
		},

		templates() {
			const emptyTemplate = {
				basename: t('files', 'Blank'),
				fileid: -1,
				filename: t('files', 'Blank'),
				hasPreview: false,
				mime: this.fileTemplate?.mimetypes[0] || this.fileTemplate?.mimetypes,
			}

			return [
				emptyTemplate,
				...this.fileTemplate.templates,
			]
		},

		selectedTemplate() {
			return this.templates.find((template) => template.fileid === this.checked)
		},

		style() {
			const border = 2
			const margin = 8
			// Fallback to 16:9 landscape ratio
			const ratio = this.fileTemplate.ratio ? this.fileTemplate.ratio : 1.77
			// Landscape templates should be wider than tall ones
			// We fit 3 templates per row at max for landscape and 4 for portrait
			const width = ratio > 1 ? margin * 30 : margin * 20

			return {
				'--margin': margin + 'px',
				'--width': width + 'px',
				'--border': border + 'px',
				'--fullwidth': width + 2 * (margin + border) + 'px',
				'--height': this.fileTemplate.ratio ? Math.round(width / this.fileTemplate.ratio) + 'px' : null,
			}
		},
	},

	watch: {
		fileTemplate: {
			deep: true,
			immediate: true,
			handler(value) {
				this.newFileTitle = value.label + value.extension
			},
		},

		selectedTemplate: {
			deep: true,
			handler(value) {
				if (value.fileid === -1) {
					this.newFileTitle = this.fileTemplate.label + this.fileTemplate.extension
				} else {
					this.newFileTitle = value.basename
				}
			},
		},
	},

	mounted() {
		this.$nextTick(() => {
			this.$refs.textField.$refs.inputField.$refs.input.select()
		})
	},

	methods: {
		t,

		// Create text file and share it to a conversation
		async handleCreateNewFile() {
			this.loading = true
			this.newFileError = ''
			let filePath = this.$store.getters.getAttachmentFolder() + '/' + this.newFileTitle.replace('/', '')

			if (!filePath.endsWith(this.fileTemplate.extension)) {
				filePath += this.fileTemplate.extension
			}

			let fileData
			try {
				const response = await createNewFile({
					filePath,
					templatePath: this.selectedTemplate.fileid === -1 ? undefined : this.selectedTemplate?.filename,
					templateType: this.selectedTemplate.fileid === -1 ? undefined : this.selectedTemplate?.templateType,
				})
				fileData = response.data.ocs.data
			} catch (error) {
				console.error('Error while creating file', error)
				if (error?.response?.data?.ocs?.meta?.message) {
					showError(error.response.data.ocs.meta.message)
					this.newFileError = error.response.data.ocs.meta.message
				} else {
					showError(t('spreed', 'Error while creating file'))
				}
				this.loading = false
				return
			}

			await this.$store.dispatch('shareFile', { token: this.token, path: filePath })

			this.loading = false

			this.openViewer(filePath, [fileData], fileData)

			this.closeModal()
		},

		closeModal() {
			this.newFileError = ''
			this.newFileTitle = t('spreed', 'New file')
			this.$emit('dismiss')
		},

		/**
		 * Manages the radio template picker change
		 *
		 * @param {number} fileid the selected template file id
		 */
		onCheck(fileid) {
			this.checked = fileid
		},
	},
}
</script>

<style lang="scss" scoped>
.templates-picker {
	&__form {
		width: 100%;
	}

	&__list {
		margin-top: 20px;
		display: grid;
		grid-gap: calc(var(--margin) * 2);
		grid-auto-columns: 1fr;
		// We want maximum 5 columns. Putting 6 as we don't count the grid gap. So it will always be lower than 6
		max-width: calc(var(--fullwidth) * 6);
		grid-template-columns: repeat(auto-fit, var(--fullwidth));
		// Make sure all rows are the same height
		grid-auto-rows: 1fr;
		// Center the columns set
		justify-content: center;
	}
}
</style>
