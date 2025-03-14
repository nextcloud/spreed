<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog :open.sync="showSettings"
		:name="t('spreed', 'Talk settings')"
		show-navigation>
		<!-- Custom settings sections registered via OCA.Talk.Settings -->
		<NcAppSettingsSection v-for="{ id, name, element } in customSettingsSections"
			:id="id"
			:key="id"
			:name="name"
			class="app-settings-section">
			<component :is="element" />
		</NcAppSettingsSection>

		<NcAppSettingsSection id="devices"
			:name="t('spreed', 'Choose devices')"
			class="app-settings-section">
			<MediaDevicesPreview />
			<NcCheckboxRadioSwitch v-if="supportStartWithoutMedia"
				id="call-media"
				:model-value="startWithoutMediaEnabled"
				:disabled="mediaLoading"
				type="switch"
				class="checkbox call-media"
				@update:model-value="toggleStartWithoutMedia">
				{{ t('spreed', 'Turn off camera and microphone by default when joining a call') }}
			</NcCheckboxRadioSwitch>
		</NcAppSettingsSection>
		<NcAppSettingsSection v-if="!isGuest"
			id="attachments"
			:name="t('spreed', 'Attachments folder')"
			class="app-settings-section">
			<em class="app-settings-section__hint">
				{{ locationHint }}
			</em>
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
		<NcAppSettingsSection v-if="!isGuest && supportConversationsListStyle"
			id="talk_appearance"
			:name="t('spreed', 'Appearance')"
			class="app-settings-section">
			<NcCheckboxRadioSwitch id="conversations_list_style"
				:model-value="conversationsListStyle"
				:disabled="appearanceLoading"
				type="switch"
				class="checkbox"
				@update:modelValue="toggleConversationsListStyle">
				{{ t('spreed', 'Show conversations list in compact mode') }}
			</NcCheckboxRadioSwitch>
		</NcAppSettingsSection>
		<NcAppSettingsSection v-if="!isGuest"
			id="privacy"
			:name="t('spreed', 'Privacy')"
			class="app-settings-section">
			<NcCheckboxRadioSwitch id="read_status_privacy"
				:model-value="readStatusPrivacyIsPublic"
				:disabled="privacyLoading"
				type="switch"
				class="checkbox"
				@update:model-value="toggleReadStatusPrivacy">
				{{ t('spreed', 'Share my read-status and show the read-status of others') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="supportTypingStatus"
				id="typing_status_privacy"
				:model-value="typingStatusPrivacyIsPublic"
				:disabled="privacyLoading"
				type="switch"
				class="checkbox"
				@update:model-value="toggleTypingStatusPrivacy">
				{{ t('spreed', 'Share my typing-status and show the typing-status of others') }}
			</NcCheckboxRadioSwitch>
		</NcAppSettingsSection>
		<NcAppSettingsSection id="sounds"
			:name="t('spreed', 'Sounds')"
			class="app-settings-section">
			<NcCheckboxRadioSwitch id="play_sounds"
				:model-value="shouldPlaySounds"
				:disabled="playSoundsLoading"
				type="switch"
				class="checkbox"
				@update:model-value="togglePlaySounds">
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
			<template v-if="serverSupportsBackgroundBlurred">
				<NcCheckboxRadioSwitch id="blur-call-background"
					:model-value="isBackgroundBlurred === 'yes'"
					:indeterminate="isBackgroundBlurred === ''"
					type="checkbox"
					class="checkbox"
					disabled>
					{{ t('spreed', 'Blur background image in the call (may increase GPU load)') }}
				</NcCheckboxRadioSwitch>
				<a :href="themingUrl"
					target="_blank"
					rel="noreferrer nofollow"
					class="external">
					{{ t('spreed', 'Background blur for Nextcloud instance can be adjusted in the theming settings.') }} ↗
				</a>
			</template>
			<NcCheckboxRadioSwitch v-else
				id="blur-call-background"
				:model-value="isBackgroundBlurred !== 'false'"
				type="switch"
				class="checkbox"
				@update:model-value="toggleBackgroundBlurred">
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
					<dt><kbd>{{ CmdOrCtrl }}</kbd> + <kbd>↑</kbd></dt>
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
					<dt><kbd>{{ CmdOrCtrl }}</kbd> + <kbd>F</kbd></dt>
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
				<div>
					<dt><kbd>{{ t('spreed', 'Mouse wheel') }}</kbd></dt>
					<dd class="shortcut-description">
						{{ t('spreed', 'Zoom-in / zoom-out a screen share') }}
					</dd>
				</div>
			</dl>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { ref } from 'vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { FilePickerVue } from '@nextcloud/dialogs/filepicker.js'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import MediaDevicesPreview from './MediaDevicesPreview.vue'

import { CONVERSATION, PRIVACY } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useCustomSettings } from '../../services/SettingsAPI.ts'
import { setUserConfig } from '../../services/settingsService.ts'
import { useSettingsStore } from '../../stores/settings.js'
import { useSoundsStore } from '../../stores/sounds.js'
import { isMac } from '../../utils/browserCheck.ts'
import { satisfyVersion } from '../../utils/satisfyVersion.ts'

const serverVersion = loadState('core', 'config', {}).version ?? '29.0.0.0'
const serverSupportsBackgroundBlurred = satisfyVersion(serverVersion, '29.0.4.0')

const isBackgroundBlurredState = serverSupportsBackgroundBlurred
	? loadState('spreed', 'force_enable_blur_filter', '') // 'yes', 'no', ''
	: BrowserStorage.getItem('background-blurred') // 'true', 'false', null
const supportTypingStatus = getTalkConfig('local', 'chat', 'typing-privacy') !== undefined
const supportStartWithoutMedia = getTalkConfig('local', 'call', 'start-without-media') !== undefined
const supportConversationsListStyle = getTalkConfig('local', 'conversations', 'list-style') !== undefined

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
		const soundsStore = useSoundsStore()
		const { customSettingsSections } = useCustomSettings()
		const isBackgroundBlurred = ref(isBackgroundBlurredState)
		const CmdOrCtrl = isMac ? 'Cmd' : 'Ctrl'

		return {
			CmdOrCtrl,
			settingsStore,
			soundsStore,
			supportTypingStatus,
			isBackgroundBlurred,
			serverSupportsBackgroundBlurred,
			customSettingsSections,
			supportStartWithoutMedia,
			supportConversationsListStyle,
		}
	},

	data() {
		return {
			showSettings: false,
			showFilePicker: false,
			attachmentFolderLoading: true,
			appearanceLoading: false,
			privacyLoading: false,
			playSoundsLoading: false,
			mediaLoading: false,
		}
	},

	computed: {
		shouldPlaySounds() {
			return this.soundsStore.shouldPlaySounds
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

		startWithoutMediaEnabled() {
			return this.settingsStore.startWithoutMedia
		},

		conversationsListStyle() {
			return this.settingsStore.conversationsListStyle !== CONVERSATION.LIST_STYLE.TWO_LINES
		},

		settingsUrl() {
			return generateUrl('/settings/user/notifications')
		},

		themingUrl() {
			return generateUrl('/settings/user/theming')
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

	async created() {
		const blurred = BrowserStorage.getItem('background-blurred')
		if (serverSupportsBackgroundBlurred) {
			// Blur is handled by theming app, migrating
			if (blurred === 'false' && isBackgroundBlurredState === '') {
				console.debug('Blur was disabled intentionally, propagating last choice to server')
				await setUserConfig('theming', 'force_enable_blur_filter', 'no')
			}
			BrowserStorage.removeItem('background-blurred')
		} else if (blurred === null) {
			// Fallback to BrowserStorage
			BrowserStorage.setItem('background-blurred', 'true')
		}
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
		this.attachmentFolderLoading = false
	},

	methods: {
		t,
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
				showError(t('spreed', 'Error while setting attachment folder'))
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
				showError(t('spreed', 'Error while setting read status privacy'))
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
				showError(t('spreed', 'Error while setting typing status privacy'))
			}
			this.privacyLoading = false
		},

		async toggleConversationsListStyle(value) {
			this.appearanceLoading = true
			try {
				await this.settingsStore.setConversationsListStyle(
					value ? CONVERSATION.LIST_STYLE.COMPACT : CONVERSATION.LIST_STYLE.TWO_LINES
				)
				showSuccess(t('spreed', 'Your personal setting has been saved'))
			} catch (exception) {
				showError(t('spreed', 'Error while setting personal setting'))
			}
			this.appearanceLoading = false
		},

		/**
		 * Fallback method for versions before v29.0.4
		 * @param {boolean} value whether background should be blurred
		 */
		toggleBackgroundBlurred(value) {
			this.isBackgroundBlurred = value.toString()
			BrowserStorage.setItem('background-blurred', this.isBackgroundBlurred)
			emit('set-background-blurred', value)
		},

		async togglePlaySounds() {
			this.playSoundsLoading = true
			try {
				try {
					await this.soundsStore.setShouldPlaySounds(!this.shouldPlaySounds)
				} catch (e) {
					showError(t('spreed', 'Failed to save sounds setting'))
				}
				showSuccess(t('spreed', 'Sounds setting saved'))
			} catch (exception) {
				showError(t('spreed', 'Error while saving sounds setting'))
			}
			this.playSoundsLoading = false
		},

		async toggleStartWithoutMedia(value) {
			this.mediaLoading = true
			try {
				await this.settingsStore.setStartWithoutMedia(value)
				showSuccess(t('spreed', 'Your default media state has been saved'))
			} catch (exception) {
				showError(t('spreed', 'Error while setting default media state'))
			} finally {
				this.mediaLoading = false
			}
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

	// Copy-pasted styles from NcInputField
	&__input {
		width: 300px;
		height: var(--default-clickable-area);
		line-height: var(--default-clickable-area);
		padding-inline: 12px 6px;
		border: var(--border-width-input, 2px) solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius-large);
		font-size: var(--default-font-size);
		text-overflow: ellipsis;
		opacity: 0.7;
		color: var(--color-main-text);
		background-color: var(--color-main-background);
		cursor: pointer;
	}

	.shortcut-description {
		width: calc(100% - 160px);
	}
}

.call-media {
	margin: calc(3 * var(--default-grid-baseline)) 0;
}

</style>
