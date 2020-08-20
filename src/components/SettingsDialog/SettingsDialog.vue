<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @license GNU AGPL version 3 or any later version
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
-->

<template>
	<Modal v-if="showSettings"
		@close="showSettings = false">
		<div class="wrapper">
			<div class="app-settings-section">
				<h2 class="app-setting-section__title">
					{{ t('spreed', 'Choose devices') }}
				</h2>
				<MediaDevicesPreview :enabled="enableMediaDevicesPreview" />
			</div>
			<div v-if="!isGuest" class="app-settings-section last">
				<h2 class="app-setting-section__title">
					{{ t('spreed', 'Attachments folder') }}
				</h2>
				<h3 class="app-settings-section__hint">
					{{ defaultLocationHint }}
				</h3>
				<input
					type="text"
					class="app-settings-section__input"
					:value="attachmentFolder"
					:disabled="attachmentFolderLoading"
					@click="selectAttachmentFolder">
			</div>
			<div class="app-settings-section last">
				<h2 class="app-setting-section__title">
					{{ t('spreed', 'Preview') }}
				</h2>
				<MediaDevicesPreview :enabled="enableMediaDevicesPreview" />
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { setAttachmentFolder } from '../../services/settingsService'
import { EventBus } from '../../services/EventBus'
import MediaDevicesPreview from '../MediaDevicesPreview'
import isInCall from '../../mixins/isInCall'

export default {
	name: 'SettingsDialog',

	components: {
		Modal,
		MediaDevicesPreview,
	},

	mixins: [isInCall],

	data() {
		return {
			showSettings: false,
			attachmentFolderLoading: true,
		}
	},

	computed: {
		attachmentFolder() {
			return this.$store.getters.getAttachmentFolder()
		},

		defaultLocationHint() {
			return t('spreed', `Choose in which folder talk attachments should be saved`)
		},

		enableMediaDevicesPreview() {
			return !this.isInCall
		},
	},

	mounted() {
		EventBus.$on('show-settings', this.handleShowSettings)
		this.attachmentFolderLoading = false
	},

	methods: {

		selectAttachmentFolder() {
			const picker = getFilePickerBuilder(t('spreed', 'Select default location for attachments'))
				.setMultiSelect(false)
				.setModal(true)
				.setType(1)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories()
				.startAt(this.attachmentFolder)
				.build()
			picker.pick()
				.then(async(path) => {
					console.debug(`Path '${path}' selected for talk attachments`)
					if (path !== '' && !path.startsWith('/')) {
						throw new Error(t('spreed', 'Invalid path selected'))
					}

					const oldFolder = this.attachmentFolder
					this.attachmentFolderLoading = true
					try {
						this.$store.commit('setAttachmentFolder', path)
						await setAttachmentFolder(path)
					} catch (exception) {
						showError(t('spreed', 'Error while setting attachment folder'))
						this.$store.commit('setAttachmentFolder', oldFolder)
					}
					this.attachmentFolderLoading = false
				})
		},

		handleShowSettings(showSettings) {
			this.showSettings = showSettings
		},

		beforeDestroy() {
			EventBus.$off('show-settings')
		},
	},
}
</script>

<style lang="scss" scoped>

.wrapper {
	overflow-y: scroll;
	padding: 20px;
}

.app-settings-section {
	margin-bottom: 80px;
	&.last {
		margin-bottom: 0px;
	}
	&__title {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
	&__hint {
		color: var(--color-text-lighter);
		padding: 8px 0;
	}
	&__input {
		width: 100%;
	}
}

::v-deep .modal-container {
	display: flex !important;
	flex-direction: column;
	min-width: 250px !important;
	max-width: 500px !important;
	padding: 8px !important;
}

</style>
