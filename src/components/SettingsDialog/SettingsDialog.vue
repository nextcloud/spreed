<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog :open.sync="showSettings"
		:name="t('spreed', 'Talk settings')"
		:show-navigation="true"
		first-selected-section="keyboard shortcuts"
		:container="container">
		<NcAppSettingsSection id="devices"
			:name="t('spreed', 'Choose devices')"
			class="app-settings-section">
			<MediaDevicesPreview />
		</NcAppSettingsSection>
		<NcAppSettingsSection v-if="!isGuest"
			id="attachments"
			:name="t('spreed', 'Attachments folder')"
			class="app-settings-section">
			<h3 class="app-settings-section__hint">
				{{ locationHint }}
			</h3>
			<div class="app-settings-section__wrapper">
				<p class="app-settings-section__input" @click="showFilePicker = true">
					{{ attachmentFolder }}
				</p>
				<NcButton type="primary"
					@click="showFilePicker = true">
					{{ t('spreed', 'Browse …') }}
				</NcButton>
				<FilePickerVue v-if="showFilePicker"
					:name="t('spreed', 'Select location for attachments')"
					:path="attachmentFolder"
					container=".app-settings-section__wrapper"
					:buttons="filePickerButtons"
					:multiselect="false"
					:mimetype-filter="['httpd/unix-directory']"
					allow-pick-directory
					@close="showFilePicker = false" />
			</div>
		</NcAppSettingsSection>
		<NcAppSettingsSection v-if="!isGuest"
			id="privacy"
			:name="t('spreed', 'Privacy')"
			class="app-settings-section">
			<NcCheckboxRadioSwitch id="read_status_privacy"
				:checked="readStatusPrivacyIsPublic"
				:disabled="privacyLoading"
				type="switch"
				class="checkbox"
				@update:checked="toggleReadStatusPrivacy">
				{{ t('spreed', 'Share my read-status and show the read-status of others') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="supportTypingStatus"
				id="typing_status_privacy"
				:checked="typingStatusPrivacyIsPublic"
				:disabled="privacyLoading"
				type="switch"
				class="checkbox"
				@update:checked="toggleTypingStatusPrivacy">
				{{ t('spreed', 'Share my typing-status and show the typing-status of others') }}
			</NcCheckboxRadioSwitch>
		</NcAppSettingsSection>
		<NcAppSettingsSection id="sounds"
			:name="t('spreed', 'Sounds')"
			class="app-settings-section">
			<NcCheckboxRadioSwitch id="play_sounds"
				:checked="playSounds"
				:disabled="playSoundsLoading"
				type="switch"
				class="checkbox"
				@update:checked="togglePlaySounds">
				{{ t('spreed', 'Play sounds when participants join or leave a call') }}
			</NcCheckboxRadioSwitch>
			<em>{{ t('spreed', 'Sounds can currently not be played on iPad and iPhone devices due to technical restrictions by the manufacturer.') }}</em>

			<a :href="settingsUrl"
				target="_blank"
				rel="noreferrer nofollow"
				class="external">
				{{ t('spreed', 'Sounds for chat and call notifications can be adjusted in the personal settings.') }} ↗
			</a>
		</NcAppSettingsSection>
		<NcAppSettingsSection id="performance"
			:name="t('spreed', 'Performance')"
			class="app-settings-section">
			<NcCheckboxRadioSwitch id="blur-call-background"
				:checked="isBackgroundBlurred"
				type="switch"
				class="checkbox"
				@update:checked="toggleBackgroundBlurred">
				{{ t('spreed', 'Blur background image in the call (may increase GPU load)') }}
			</NcCheckboxRadioSwitch>
		</NcAppSettingsSection>
		<NcAppSettingsSection v-if="!disableKeyboardShortcuts"
			id="shortcuts"
			:name="t('spreed', 'Keyboard shortcuts')">
			<em>{{ t('spreed', 'Speed up your Talk experience with these quick shortcuts.') }}</em>

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
					<dt><kbd>Ctrl</kbd> + <kbd>↑</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Edit your last message') }}
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
						{{ t('spreed', 'Camera on and off') }}
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
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { getCapabilities } from '@nextcloud/capabilities'
// eslint-disable-next-line
// import { showError, showSuccess } from '@nextcloud/dialogs'
import { FilePickerVue } from '@nextcloud/dialogs/filepicker.js'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'

import NcAppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import MediaDevicesPreview from './MediaDevicesPreview.vue'

import { PRIVACY } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import { useSettingsStore } from '../../stores/settings.js'

const supportTypingStatus = getCapabilities()?.spreed?.config?.chat?.['typing-privacy'] !== undefined

export default {
	name: 'SettingsDialog',

	components: {
		FilePickerVue,
		MediaDevicesPreview,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		NcCheckboxRadioSwitch,
	},

	setup() {
		const settingsStore = useSettingsStore()

		return {
			settingsStore,
			supportTypingStatus,
		}
	},

	data() {
		return {
			showSettings: false,
			showFilePicker: false,
			attachmentFolderLoading: true,
			privacyLoading: false,
			playSoundsLoading: false,
			isBackgroundBlurred: true,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		playSounds() {
			return this.$store.getters.playSounds
		},

		attachmentFolder() {
			return this.$store.getters.getAttachmentFolder()
		},

		locationHint() {
			return t('spreed', 'Choose the folder in which attachments should be saved.')
		},

		isGuest() {
			return !this.$store.getters.getUserId()
		},

		readStatusPrivacyIsPublic() {
			return this.settingsStore.readStatusPrivacy === PRIVACY.PUBLIC
		},

		typingStatusPrivacyIsPublic() {
			return this.settingsStore.typingStatusPrivacy === PRIVACY.PUBLIC
		},

		settingsUrl() {
			return generateUrl('/settings/user/notifications')
		},

		disableKeyboardShortcuts() {
			return OCP.Accessibility.disableKeyboardShortcuts()
		},

		filePickerButtons() {
			return [{
				label: t('spreed', 'Choose'),
				callback: (nodes) => this.selectAttachmentFolder(nodes),
				type: 'primary'
			}]
		},
	},

	created() {
		const blurred = BrowserStorage.getItem('background-blurred')
		if (blurred === null) {
			BrowserStorage.setItem('background-blurred', 'true')
		}

		this.isBackgroundBlurred = blurred !== 'false'
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
		this.attachmentFolderLoading = false
	},

	methods: {
		async selectAttachmentFolder(nodes) {
			const path = nodes[0]?.path
			if (!path) {
				return
			}

			console.debug(`Path '${path}' selected for talk attachments`)
			if (path !== '' && !path.startsWith('/')) {
				throw new Error(t('spreed', 'Invalid path selected'))
			}

			this.attachmentFolderLoading = true
			try {
				this.$store.dispatch('setAttachmentFolder', path)
			} catch (exception) {
				window.OCP.Toast.error(t('spreed', 'Error while setting attachment folder'))
			}
			this.attachmentFolderLoading = false
		},

		async toggleReadStatusPrivacy() {
			this.privacyLoading = true
			try {
				await this.settingsStore.updateReadStatusPrivacy(
					this.readStatusPrivacyIsPublic ? PRIVACY.PRIVATE : PRIVACY.PUBLIC
				)
				showSuccess(t('spreed', 'Your privacy setting has been saved'))
			} catch (exception) {
				window.OCP.Toast.error(t('spreed', 'Error while setting read status privacy'))
			}
			this.privacyLoading = false
		},

		async toggleTypingStatusPrivacy() {
			this.privacyLoading = true
			try {
				await this.settingsStore.updateTypingStatusPrivacy(
					this.typingStatusPrivacyIsPublic ? PRIVACY.PRIVATE : PRIVACY.PUBLIC
				)
				showSuccess(t('spreed', 'Your privacy setting has been saved'))
			} catch (exception) {
				window.OCP.Toast.error(t('spreed', 'Error while setting typing status privacy'))
			}
			this.privacyLoading = false
		},

		toggleBackgroundBlurred(value) {
			this.isBackgroundBlurred = value
			BrowserStorage.setItem('background-blurred', value)
			emit('set-background-blurred', value)
		},

		async togglePlaySounds() {
			this.playSoundsLoading = true
			try {
				try {
					await this.$store.dispatch('setPlaySounds', !this.playSounds)
				} catch (e) {
					window.OCP.Toast.error(t('spreed', 'Failed to save sounds setting'))
				}
				showSuccess(t('spreed', 'Sounds setting saved'))
			} catch (exception) {
				window.OCP.Toast.error(t('spreed', 'Error while saving sounds setting'))
			}
			this.playSoundsLoading = false
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
		color: var(--color-text-maxcontrast);
		padding: 8px 0;
	}

	&__wrapper {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__input {
		width: 300px;
		height: var(--default-clickable-area);
		padding: 10px 6px 10px 12px;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius-large);
		text-overflow: ellipsis;
		opacity: 0.7;
		cursor: pointer;
	}

	.shortcut-description {
		width: calc(100% - 160px);
	}
}

</style>
