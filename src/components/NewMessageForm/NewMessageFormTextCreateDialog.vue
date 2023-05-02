<!--
  - @copyright Copyright (c) 2022, Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<NcModal size="normal"
		:container="container"
		class="templates-picker"
		@close="closeModal">
		<div class="new-text-file">
			<h2>
				{{ t('spreed', 'Create and share a new file') }}
			</h2>
			<form class="new-text-file__form templates-picker__form"
				:style="style"
				@submit.prevent="handleCreateTextFile">
				<NcTextField id="new-file-form-name"
					ref="textField"
					:error="!!newFileError"
					:helper-text="newFileError"
					:label="t('spreed', 'Name of the new file')"
					:placeholder="textFileTitle"
					:value="textFileTitle"
					@update:value="updateTextFileTitle" />

				<template v-if="fileTemplate.templates.length">
					<ul class="templates-picker__list">
						<NewMessageFormTemplatePreview v-bind="emptyTemplate"
							:checked="checked === emptyTemplate.fileid"
							@check="onCheck" />

						<NewMessageFormTemplatePreview v-for="template in fileTemplate.templates"
							:key="template.fileid"
							v-bind="template"
							:checked="checked === template.fileid"
							:ratio="fileTemplate.ratio"
							@check="onCheck" />
					</ul>
				</template>

				<div class="new-text-file__buttons">
					<NcButton type="tertiary"
						@click="closeModal">
						{{ t('spreed', 'Close') }}
					</NcButton>
					<NcButton type="primary"
						@click="handleCreateTextFile">
						{{ t('spreed', 'Create file') }}
					</NcButton>
				</div>
			</form>
		</div>
	</NcModal>
</template>

<script>
import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import NewMessageFormTemplatePreview from './NewMessageFormTemplatePreview.vue'

import { useViewer } from '../../composables/useViewer.js'
import { createTextFile, shareFile } from '../../services/filesSharingServices.js'

export default {
	name: 'NewMessageFormTextCreateDialog',

	components: {
		NcButton,
		NewMessageFormTemplatePreview,
		NcModal,
		NcTextField,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		container: {
			type: String,
			required: true,
		},

		showTextFileDialog: {
			type: Number,
			required: true,
		},
	},

	setup() {
		const { openViewer } = useViewer()
		return { openViewer }
	},

	data() {
		return {
			textFileTitle: t('spreed', 'New file'),
			newFileError: '',
			checked: -1,
		}
	},

	computed: {
		fileTemplateOptions() {
			return this.$store.getters.getFileTemplates()
		},

		fileTemplate() {
			return this.fileTemplateOptions[this.showTextFileDialog]
		},

		emptyTemplate() {
			return {
				basename: t('files', 'Blank'),
				fileid: -1,
				filename: t('files', 'Blank'),
				hasPreview: false,
				mime: this.fileTemplate?.mimetypes[0] || this.fileTemplate?.mimetypes,
			}
		},

		selectedTemplate() {
			return this.fileTemplate.templates.find(template => template.fileid === this.checked)
		},

		style() {
			const border = 2
			const margin = 8
			const width = margin * 20

			return {
				'--margin': margin + 'px',
				'--width': width + 'px',
				'--border': border + 'px',
				'--fullwidth': width + 2 * (margin + border) + 'px',
				'--height': this.fileTemplate.ratio ? Math.round(width / this.fileTemplate.ratio) + 'px' : null,
			}
		},
	},

	mounted() {
		const fileTemplate = this.fileTemplateOptions[this.showTextFileDialog]
		this.textFileTitle = fileTemplate.label + fileTemplate.extension
		this.$nextTick(() => {
			this.$refs.textField.$refs.inputField.$refs.input.select()
		})
	},

	methods: {
		updateTextFileTitle(value) {
			this.textFileTitle = value
		},

		// Create text file and share it to a conversation
		async handleCreateTextFile() {
			this.newFileError = ''
			let filePath = this.$store.getters.getAttachmentFolder() + '/' + this.textFileTitle.replace('/', '')

			if (!filePath.endsWith(this.fileTemplate.extension)) {
				filePath += this.fileTemplate.extension
			}

			let fileData
			try {
				const response = await createTextFile(
					filePath,
					this.selectedTemplate?.filename,
					this.selectedTemplate?.templateType,
				)
				fileData = response.data.ocs.data
			} catch (error) {
				console.error('Error while creating file', error)
				if (error?.response?.data?.ocs?.meta?.message) {
					showError(error.response.data.ocs.meta.message)
					this.newFileError = error.response.data.ocs.meta.message
				} else {
					showError(t('spreed', 'Error while creating file'))
				}
				return
			}

			await shareFile(filePath, this.token, '', '')

			this.openViewer(filePath, [fileData])

			this.closeModal()
		},

		closeModal() {
			this.newFileError = ''
			this.textFileTitle = t('spreed', 'New file')
			this.$emit('dismiss-text-file-creation')
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
.new-text-file {
	display: flex;
	justify-content: center;
	align-items: center;
	flex-direction: column;
	gap: 28px;
	padding: 20px;

	&__buttons {
		display: flex;
		gap: 4px;
		justify-content: center;
		margin-top: 20px;
	}

	&__form {
		width: 100%;

		.templates-picker__list {
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
}
</style>
