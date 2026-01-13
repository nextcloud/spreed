/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { PRIVACY } from '../constants.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import {
	setAttachmentFolder,
	setBlurVirtualBackground,
	setChatStyle,
	setConversationsListStyle,
	setLiveTranscriptionTargetLanguageId,
	setReadStatusPrivacy,
	setStartWithoutMedia,
	setTypingStatusPrivacy,
} from '../services/settingsService.ts'

type PRIVACY_KEYS = typeof PRIVACY[keyof typeof PRIVACY]
type LIST_STYLE_OPTIONS = 'two-lines' | 'compact'
type CHAT_STYLE_OPTIONS = 'split' | 'unified'

const supportChatStyle = getTalkConfig('local', 'chat', 'style') !== undefined

const hasUserAccount = Boolean(getCurrentUser()?.uid)

/**
 * Store for shared items shown in RightSidebar
 */
export const useSettingsStore = defineStore('settings', () => {
	const readStatusPrivacy = ref<PRIVACY_KEYS>(loadState('spreed', 'read_status_privacy', PRIVACY.PRIVATE))
	const typingStatusPrivacy = ref<PRIVACY_KEYS>(loadState('spreed', 'typing_privacy', PRIVACY.PRIVATE))
	const showMediaSettings = ref<boolean>(BrowserStorage.getItem('showMediaSettings') !== 'false')
	const noiseSuppression = ref<boolean>(BrowserStorage.getItem('noiseSuppression') !== 'false')
	const echoCancellation = ref<boolean>(BrowserStorage.getItem('echoCancellation') !== 'false')
	const autoGainControl = ref<boolean>(BrowserStorage.getItem('autoGainControl') !== 'false')
	const startWithoutMedia = ref<boolean | undefined>(getTalkConfig('local', 'call', 'start-without-media'))
	const blurVirtualBackgroundEnabled = ref<boolean | undefined>(getTalkConfig('local', 'call', 'blur-virtual-background'))
	const conversationsListStyle = ref<LIST_STYLE_OPTIONS | undefined>(getTalkConfig('local', 'conversations', 'list-style'))
	const chatStyle = ref<CHAT_STYLE_OPTIONS | undefined>(supportChatStyle ? (getTalkConfig('local', 'chat', 'style') ?? 'split') : 'unified')

	const liveTranscriptionTargetLanguageId = ref<string | undefined>(getTalkConfig('local', 'call', 'live-transcription-target-language-id'))
	if (!hasUserAccount && BrowserStorage.getItem('liveTranscriptionTargetLanguageId') !== null) {
		liveTranscriptionTargetLanguageId.value = BrowserStorage.getItem('liveTranscriptionTargetLanguageId') as string
	}

	const attachmentFolder = ref<string>(loadState('spreed', 'attachment_folder', ''))
	const attachmentFolderFreeSpace = ref<number>(loadState('spreed', 'attachment_folder_free_space', 0))

	/**
	 * Update the read status privacy for the user
	 *
	 * @param privacy - new selected privacy
	 */
	async function updateReadStatusPrivacy(privacy: PRIVACY_KEYS) {
		await setReadStatusPrivacy(privacy)
		readStatusPrivacy.value = privacy
	}

	/**
	 * Update the typing status privacy for the user
	 *
	 * @param privacy - new selected privacy
	 */
	async function updateTypingStatusPrivacy(privacy: PRIVACY_KEYS) {
		await setTypingStatusPrivacy(privacy)
		typingStatusPrivacy.value = privacy
	}

	/**
	 * Update the show media settings for the user
	 *
	 * @param value - new selected state
	 */
	function setShowMediaSettings(value: boolean) {
		BrowserStorage.setItem('showMediaSettings', value.toString())
		showMediaSettings.value = value
	}

	/**
	 * Update the noise suppression settings for the user
	 *
	 * @param value - new selected state
	 */
	function setNoiseSuppression(value: boolean) {
		BrowserStorage.setItem('noiseSuppression', value.toString())
		noiseSuppression.value = value
	}

	/**
	 * Update the echo cancellation settings for the user
	 *
	 * @param value - new selected state
	 */
	function setEchoCancellation(value: boolean) {
		BrowserStorage.setItem('echoCancellation', value.toString())
		echoCancellation.value = value
	}

	/**
	 * Update the auto gain settings for the user
	 *
	 * @param value - new selected state
	 */
	function setAutoGainControl(value: boolean) {
		BrowserStorage.setItem('autoGainControl', value.toString())
		autoGainControl.value = value
	}

	/**
	 * Update the blur virtual background setting for the user
	 *
	 * @param value - new selected state
	 */
	async function setBlurVirtualBackgroundEnabled(value: boolean) {
		await setBlurVirtualBackground(value)
		blurVirtualBackgroundEnabled.value = value
	}

	/**
	 * Update the start without media setting for the user
	 *
	 * @param value - new selected state
	 */
	async function updateStartWithoutMedia(value: boolean) {
		await setStartWithoutMedia(value)
		startWithoutMedia.value = value
	}

	/**
	 * Update the conversations list style setting for the user
	 *
	 * @param value - new selected state
	 */
	async function updateConversationsListStyle(value: LIST_STYLE_OPTIONS) {
		await setConversationsListStyle(value)
		conversationsListStyle.value = value
	}

	/**
	 * Update the attachment folder setting for the user
	 *
	 * @param value - new folder to upload attachments to
	 */
	async function updateAttachmentFolder(value: string) {
		await setAttachmentFolder(value)
		attachmentFolder.value = value
	}

	/**
	 * Update the conversations list style setting for the user
	 *
	 * @param value - new selected state
	 */
	async function updateChatStyle(value: CHAT_STYLE_OPTIONS) {
		await setChatStyle(value)
		chatStyle.value = value
	}

	/**
	 * Update the live transcription target language id setting for the user
	 *
	 * @param value - new live transcription target language id
	 */
	async function updateLiveTranscriptionTargetLanguageId(value: string) {
		await setLiveTranscriptionTargetLanguageId(hasUserAccount, value)
		liveTranscriptionTargetLanguageId.value = value
	}

	return {
		readStatusPrivacy,
		typingStatusPrivacy,
		showMediaSettings,
		noiseSuppression,
		echoCancellation,
		autoGainControl,
		startWithoutMedia,
		blurVirtualBackgroundEnabled,
		conversationsListStyle,
		attachmentFolder,
		attachmentFolderFreeSpace,
		chatStyle,
		liveTranscriptionTargetLanguageId,

		updateReadStatusPrivacy,
		updateTypingStatusPrivacy,
		setShowMediaSettings,
		setNoiseSuppression,
		setEchoCancellation,
		setAutoGainControl,
		setBlurVirtualBackgroundEnabled,
		updateStartWithoutMedia,
		updateConversationsListStyle,
		updateAttachmentFolder,
		updateChatStyle,
		updateLiveTranscriptionTargetLanguageId,
	}
})
