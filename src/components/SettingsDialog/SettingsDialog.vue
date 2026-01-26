<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog
		v-model:open="showSettings"
		:name="t('spreed', 'App settings')"
		showNavigation>
		<!-- Custom settings sections registered via OCA.Talk.Settings -->
		<NcAppSettingsSection
			v-for="{ id, name, element } in customSettingsSections"
			:id="id"
			:key="id"
			:name="name">
			<component :is="element" />
		</NcAppSettingsSection>

		<NcAppSettingsSection
			id="devices"
			:name="t('spreed', 'Devices')">
			<NcFormBox>
				<NcFormBoxSwitch
					v-if="supportStartWithoutMedia"
					:modelValue="startWithoutMediaEnabled"
					:label="t('spreed', 'Turn camera and microphone off by default')"
					:disabled="mediaLoading"
					@update:modelValue="toggleStartWithoutMedia" />
				<NcFormBoxSwitch
					v-if="supportDefaultBlurVirtualBackground"
					:modelValue="settingsStore.blurVirtualBackgroundEnabled"
					:label="t('spreed', 'Blur camera background by default')"
					@update:modelValue="setBlurVirtualBackgroundEnabled" />
				<NcFormBoxSwitch
					v-if="!isGuest"
					:modelValue="hideMediaSettings"
					:label="t('spreed', 'Skip device preview before joining a call')"
					:description="t('spreed', 'Always shown if recording consent is required')"
					@update:modelValue="setHideMediaSettings" />
				<NcFormBoxButton
					:label="t('spreed', 'Microphone settings')"
					@click="openAdvancedSettings">
					<template #icon>
						<IconTune :size="20" />
					</template>
				</NcFormBoxButton>
			</NcFormBox>

			<NcButton
				variant="secondary"
				wide
				@click="openMediaSettings">
				<template #icon>
					<IconMicrophoneOutline :size="20" />
				</template>
				{{ t('spreed', 'Check devices') }}
			</NcButton>
		</NcAppSettingsSection>

		<NcAppSettingsSection
			id="talk_appearance"
			:name="t('spreed', 'Appearance & Sounds')">
			<AppearanceSettings />
		</NcAppSettingsSection>

		<NcAppSettingsSection
			v-if="!isGuest"
			id="privacy"
			:name="t('spreed', 'Privacy')">
			<NcFormBox>
				<NcFormBoxSwitch
					:modelValue="readStatusPrivacyIsPublic"
					:label="t('spreed', 'Send read receipts')"
					:description="t('spreed', 'When off, all read statuses will be hidden')"
					:disabled="privacyLoading"
					@update:modelValue="toggleReadStatusPrivacy" />
				<NcFormBoxSwitch
					v-if="supportTypingStatus"
					:modelValue="typingStatusPrivacyIsPublic"
					:label="t('spreed', 'Share typing status')"
					:description="t('spreed', 'When off, all typing indicators will be hidden')"
					:disabled="privacyLoading"
					@update:modelValue="toggleTypingStatusPrivacy" />
			</NcFormBox>
		</NcAppSettingsSection>

		<NcAppSettingsSection
			v-if="!isGuest"
			id="attachments"
			:name="t('spreed', 'Files')">
			<NcFormBoxButton
				:label="t('spreed', 'Attachments folder')"
				:description="attachmentFolder"
				invertedAccent
				@click="showFilePicker">
				<template #icon>
					<IconFolderOpenOutline :size="20" />
				</template>
			</NcFormBoxButton>
		</NcAppSettingsSection>

		<NcAppSettingsSection
			v-if="supportLiveTranslation"
			id="live_transcription"
			:name="t('spreed', 'Live transcription')">
			<NcFormBox v-slot="{ itemClass }">
				<LiveTranscriptionTargetLanguageSelect :class="itemClass" />
			</NcFormBox>
		</NcAppSettingsSection>

		<NcAppSettingsShortcutsSection
			v-if="!disableKeyboardShortcuts">
			<NcHotkeyList>
				<NcHotkey :label="t('spreed', 'Toggle full screen')" hotkey="F" />
				<NcHotkey v-if="!isGuest" :label="t('spreed', 'Return to Home screen')" hotkey="Escape" />
				<!-- FIXME Overriden by Unified Search -->
				<NcHotkey v-if="!isGuest" :label="t('spreed', 'Search')" hotkey="Control F" />
			</NcHotkeyList>

			<NcHotkeyList :label="t('spreed', 'Shortcuts while in a chat')">
				<NcHotkey :label="t('spreed', 'Focus the chat input')" hotkey="C" />
				<NcHotkey :label="t('spreed', 'Unfocus the chat input to use shortcuts')" hotkey="Escape" />
				<NcHotkey v-if="!isGuest" :label="t('spreed', 'Edit your last message')" hotkey="Control ArrowUp" />
			</NcHotkeyList>

			<NcHotkeyList :label="t('spreed', 'Shortcuts while in a call')">
				<NcHotkey :label="t('spreed', 'Camera on and off')" hotkey="V" />
				<NcHotkey :label="t('spreed', 'Microphone on and off')" hotkey="M" />
				<NcHotkey :label="t('spreed', 'Raise or lower hand')" hotkey="R" />
				<NcHotkey :label="t('spreed', 'Push to talk or push to mute')" hotkey="Space" />
				<NcHotkey :label="t('spreed', 'Zoom-in / zoom-out a screen share')">
					<template #hotkey>
						<NcKbd :symbol="t('spreed', 'Mouse wheel')" />
					</template>
				</NcHotkey>
			</NcHotkeyList>
		</NcAppSettingsShortcutsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcAppSettingsShortcutsSection from '@nextcloud/vue/components/NcAppSettingsShortcutsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcHotkey from '@nextcloud/vue/components/NcHotkey'
import NcHotkeyList from '@nextcloud/vue/components/NcHotkeyList'
import NcKbd from '@nextcloud/vue/components/NcKbd'
import IconFolderOpenOutline from 'vue-material-design-icons/FolderOpenOutline.vue'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconTune from 'vue-material-design-icons/Tune.vue'
import AdvancedAudioDialog from '../MediaSettings/AdvancedAudioDialog.vue'
import AppearanceSettings from './AppearanceSettings.vue'
import LiveTranscriptionTargetLanguageSelect from './LiveTranscriptionTargetLanguageSelect.vue'
import { CHAT_STYLE, CONVERSATION, PRIVACY } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useCustomSettings } from '../../services/SettingsAPI.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useSettingsStore } from '../../stores/settings.ts'

const disableKeyboardShortcuts = OCP.Accessibility.disableKeyboardShortcuts()

const supportTypingStatus = getTalkConfig('local', 'chat', 'typing-privacy') !== undefined
const supportStartWithoutMedia = getTalkConfig('local', 'call', 'start-without-media') !== undefined
const supportDefaultBlurVirtualBackground = getTalkConfig('local', 'call', 'blur-virtual-background') !== undefined
const supportLiveTranslation = getTalkConfig('local', 'call', 'live-translation') === true

export default {
	name: 'SettingsDialog',

	components: {
		IconTune,
		IconFolderOpenOutline,
		IconMicrophoneOutline,
		AppearanceSettings,
		LiveTranscriptionTargetLanguageSelect,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		NcAppSettingsShortcutsSection,
		NcFormBox,
		NcFormBoxButton,
		NcFormBoxSwitch,
		NcHotkeyList,
		NcHotkey,
		NcKbd,
	},

	setup() {
		const actorStore = useActorStore()
		const settingsStore = useSettingsStore()
		const { customSettingsSections } = useCustomSettings()

		return {
			disableKeyboardShortcuts,
			settingsStore,
			supportTypingStatus,
			customSettingsSections,
			supportStartWithoutMedia,
			supportDefaultBlurVirtualBackground,
			supportLiveTranslation,
			actorStore,
		}
	},

	data() {
		return {
			showSettings: false,
			attachmentFolderLoading: true,
			privacyLoading: false,
			mediaLoading: false,
		}
	},

	computed: {
		attachmentFolder() {
			return this.settingsStore.attachmentFolder
		},

		isGuest() {
			return !this.actorStore.userId
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

		hideMediaSettings() {
			return !this.settingsStore.showMediaSettings
		},
	},

	mounted() {
		subscribe('show-settings', this.handleShowSettings)
		this.attachmentFolderLoading = false
	},

	beforeUnmount() {
		unsubscribe('show-settings', this.handleShowSettings)
	},

	methods: {
		t,

		async showFilePicker() {
			const filePicker = getFilePickerBuilder(t('spreed', 'Select location for attachments'))
				.setContainer('#attachments')
				.startAt(this.attachmentFolder)
				.setMultiSelect(false)
				.allowDirectories(true)
				.addMimeTypeFilter('httpd/unix-directory')
				.addButton({
					label: t('spreed', 'Choose'),
					callback: (nodes) => this.selectAttachmentFolder(nodes),
					variant: 'primary',
				})
				.build()
			await filePicker.pickNodes()
		},

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
				await this.settingsStore.updateAttachmentFolder(path)
			} catch (exception) {
				showError(t('spreed', 'Error while setting attachment folder'))
			}
			this.attachmentFolderLoading = false
		},

		async toggleReadStatusPrivacy() {
			this.privacyLoading = true
			try {
				await this.settingsStore.updateReadStatusPrivacy(this.readStatusPrivacyIsPublic ? PRIVACY.PRIVATE : PRIVACY.PUBLIC)
			} catch (exception) {
				showError(t('spreed', 'Error while setting read status privacy'))
			}
			this.privacyLoading = false
		},

		async toggleTypingStatusPrivacy() {
			this.privacyLoading = true
			try {
				await this.settingsStore.updateTypingStatusPrivacy(this.typingStatusPrivacyIsPublic ? PRIVACY.PRIVATE : PRIVACY.PUBLIC)
			} catch (exception) {
				showError(t('spreed', 'Error while setting typing status privacy'))
			}
			this.privacyLoading = false
		},

		async toggleStartWithoutMedia(value) {
			this.mediaLoading = true
			try {
				await this.settingsStore.updateStartWithoutMedia(value)
			} catch (exception) {
				showError(t('spreed', 'Error while setting default media state'))
			} finally {
				this.mediaLoading = false
			}
		},

		handleShowSettings() {
			this.showSettings = true
		},

		setHideMediaSettings(newValue) {
			this.settingsStore.setShowMediaSettings(!newValue)
		},

		async openAdvancedSettings() {
			await spawnDialog(AdvancedAudioDialog, {
				container: '#devices',
			})
		},

		async setBlurVirtualBackgroundEnabled(value) {
			try {
				await this.settingsStore.setBlurVirtualBackgroundEnabled(value)
			} catch (error) {
				console.error('Failed to set blur background enabled:', error)
			}
		},

		openMediaSettings() {
			emit('talk:media-settings:show', 'device-check')
		},
	},
}
</script>
