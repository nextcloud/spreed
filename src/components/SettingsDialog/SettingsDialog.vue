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
	<AppSettingsDialog
		:open.sync="showSettings"
		:show-navigation="true"
		first-selected-section="keyboard shortcuts"
		:container="container">
		<AppSettingsSection :title="t('spreed', 'Choose devices')"
			class="app-settings-section">
			<MediaDevicesPreview />
		</AppSettingsSection>
		<AppSettingsSection v-if="!isGuest"
			:title="t('spreed', 'Attachments folder')"
			class="app-settings-section">
			<h3 class="app-settings-section__hint">
				{{ locationHint }}
			</h3>
			<input
				type="text"
				class="app-settings-section__input"
				:value="attachmentFolder"
				:disabled="attachmentFolderLoading"
				@click="selectAttachmentFolder">
		</AppSettingsSection>
		<AppSettingsSection v-if="!isGuest"
			:title="t('spreed', 'Privacy')"
			class="app-settings-section">
			<input id="read_status_privacy"
				:checked="readStatusPrivacyIsPublic"
				:disabled="privacyLoading"
				type="checkbox"
				name="read_status_privacy"
				class="checkbox"
				@change="toggleReadStatusPrivacy">
			<label for="read_status_privacy">{{ t('spreed', 'Share my read-status and show the read-status of others') }}</label>
		</AppSettingsSection>
		<AppSettingsSection :title="t('spreed', 'Keyboard shortcuts')">
			<p>{{ t('spreed', 'Speed up your Talk experience with these quick shortcuts.') }}</p>

			<dl>
				<div>
					<dt><kbd>C</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Focus the chat input') }}
					</dd>
				</div>
				<div>
					<dt><kbd>Esc</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Unfocus the chat input to use shortcuts') }}
					</dd>
				</div>
				<div>
					<dt><kbd>F</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Fullscreen the chat or call') }}
					</dd>
				</div>
				<div>
					<dt><kbd>Ctrl</kbd> + <kbd>F</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Search') }}
					</dd>
				</div>
			</dl>

			<h3>{{ t('spreed', 'Shortcuts while in a call') }}</h3>
			<dl>
				<div>
					<dt><kbd>V</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Video on and off') }}
					</dd>
				</div>
				<div>
					<dt><kbd>M</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Microphone on and off') }}
					</dd>
				</div>
				<div>
					<dt><kbd>{{ t('spreed', 'Space bar') }}</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Push to talk or push to mute') }}
					</dd>
				</div>
				<div>
					<dt><kbd>R</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Raise or lower hand') }}
					</dd>
				</div>
			</dl>
		</AppSettingsSection>
	</AppSettingsDialog>
</template>

<script>
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { setAttachmentFolder } from '../../services/settingsService'
import { PRIVACY } from '../../constants'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import MediaDevicesPreview from '../MediaDevicesPreview'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'

export default {
	name: 'SettingsDialog',

	components: {
		MediaDevicesPreview,
		AppSettingsDialog,
		AppSettingsSection,
	},

	data() {
		return {
			showSettings: false,
			attachmentFolderLoading: true,
			privacyLoading: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		attachmentFolder() {
			return this.$store.getters.getAttachmentFolder()
		},

		locationHint() {
			return t('spreed', 'Choose in which folder attachments should be saved.')
		},

		isGuest() {
			return !this.$store.getters.getUserId()
		},

		readStatusPrivacyIsPublic() {
			return this.readStatusPrivacy === PRIVACY.PUBLIC
		},

		readStatusPrivacy() {
			return this.$store.getters.getReadStatusPrivacy()
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
		this.attachmentFolderLoading = false
	},

	methods: {

		selectAttachmentFolder() {
			const picker = getFilePickerBuilder(t('spreed', 'Select location for attachments'))
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

		async toggleReadStatusPrivacy() {
			this.privacyLoading = true
			try {
				await this.$store.dispatch(
					'updateReadStatusPrivacy',
					this.readStatusPrivacyIsPublic ? PRIVACY.PRIVATE : PRIVACY.PUBLIC
				)
				showSuccess(t('spreed', 'Your privacy setting has been saved'))
			} catch (exception) {
				showError(t('spreed', 'Error while setting read status privacy'))
			}
			this.privacyLoading = false
		},

		handleShowSettings() {
			this.showSettings = true
		},

		beforeDestroy() {
			unsubscribe('show-settings', this.handleShowSettings)
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
		margin-bottom: 0;
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

	.shortcut-description {
		width: calc(100% - 160px);
	}
}

</style>
