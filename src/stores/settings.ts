/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ConversationPreset } from '../types/index.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { CHAT_STYLE, CONVERSATION, PRIVACY } from '../constants.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import { getTalkConfig, hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { getPresets } from '../services/conversationsService.ts'
import {
	setAttachmentFolder,
	setBlurVirtualBackground,
	setChatStyle,
	setConversationsGroupMode,
	setConversationsListStyle,
	setConversationsSortOrder,
	setLiveTranscriptionTargetLanguageId,
	setReadStatusPrivacy,
	setStartWithoutMedia,
	setTypingStatusPrivacy,
} from '../services/settingsService.ts'

type PRIVACY_KEYS = typeof PRIVACY[keyof typeof PRIVACY]
type TALK_CONFIG_PRIVACY = PRIVACY_KEYS | undefined
type CHAT_STYLE_OPTIONS = typeof CHAT_STYLE[keyof typeof CHAT_STYLE]
type LIST_STYLE_OPTIONS = typeof CONVERSATION.LIST_STYLE[keyof typeof CONVERSATION.LIST_STYLE]
type SORT_ORDER_OPTIONS = typeof CONVERSATION.SORT_ORDER[keyof typeof CONVERSATION.SORT_ORDER]
type GROUP_MODE_OPTIONS = typeof CONVERSATION.GROUP_MODE[keyof typeof CONVERSATION.GROUP_MODE]

const supportChatStyle = getTalkConfig('local', 'chat', 'style') !== undefined

const hasUserAccount = Boolean(getCurrentUser()?.uid)

/**
 * Store for shared items shown in RightSidebar
 */
export const useSettingsStore = defineStore('settings', () => {
	// Fallback to private if the config value is not found
	const readStatusPrivacy = ref<PRIVACY_KEYS>(getTalkConfig('local', 'chat', 'read-privacy') as TALK_CONFIG_PRIVACY ?? PRIVACY.PRIVATE)
	const typingStatusPrivacy = ref<PRIVACY_KEYS>(getTalkConfig('local', 'chat', 'typing-privacy') as TALK_CONFIG_PRIVACY ?? PRIVACY.PRIVATE)
	const showMediaSettings = ref<boolean>(BrowserStorage.getItem('showMediaSettings') !== 'false')
	const noiseSuppression = ref<boolean>(BrowserStorage.getItem('noiseSuppression') !== 'false')
	const noiseSuppressionWithModel = ref<'none' | 'rnnoise' | (string & {})>(BrowserStorage.getItem('noiseSuppressionWithModel') ?? 'none')
	const echoCancellation = ref<boolean>(BrowserStorage.getItem('echoCancellation') !== 'false')
	const autoGainControl = ref<boolean>(BrowserStorage.getItem('autoGainControl') !== 'false')
	const startWithoutMedia = ref<boolean | undefined>(getTalkConfig('local', 'call', 'start-without-media'))
	const blurVirtualBackgroundEnabled = ref<boolean | undefined>(getTalkConfig('local', 'call', 'blur-virtual-background'))
	const conversationsListStyle = ref<LIST_STYLE_OPTIONS | undefined>(getTalkConfig('local', 'conversations', 'list-style'))
	const chatStyle = ref<CHAT_STYLE_OPTIONS>(supportChatStyle ? (getTalkConfig('local', 'chat', 'style') ?? CHAT_STYLE.SPLIT) : CHAT_STYLE.UNIFIED)
	const sortOrder = ref<SORT_ORDER_OPTIONS>(getTalkConfig('local', 'conversations', 'sort-order') ?? CONVERSATION.SORT_ORDER.ACTIVITY)
	const groupMode = ref<GROUP_MODE_OPTIONS>(getTalkConfig('local', 'conversations', 'group-mode') ?? CONVERSATION.GROUP_MODE.NONE)

	const liveTranscriptionTargetLanguageId = ref<string | undefined>(getTalkConfig('local', 'call', 'live-transcription-target-language-id'))
	if (!hasUserAccount && BrowserStorage.getItem('liveTranscriptionTargetLanguageId') !== null) {
		liveTranscriptionTargetLanguageId.value = BrowserStorage.getItem('liveTranscriptionTargetLanguageId') as string
	}

	const attachmentFolder = ref<string>(getTalkConfig('local', 'attachments', 'folder') ?? '')
	const presets = ref<ConversationPreset[]>([])

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
	 * Update the noise suppression (with model) settings for the user
	 *
	 * @param value - new selected state
	 */
	function setNoiseSuppressionWithModel(value: 'none' | 'rnnoise' | (string & {})) {
		if (value !== 'none') {
			BrowserStorage.setItem('noiseSuppressionWithModel', value)
		} else {
			BrowserStorage.removeItem('noiseSuppressionWithModel')
		}
		noiseSuppressionWithModel.value = value
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

	/**
	 * Update the sort order for the conversation list
	 *
	 * @param value - the sort order ('activity', 'alphabetical')
	 */
	async function updateSortOrder(value: SORT_ORDER_OPTIONS) {
		await setConversationsSortOrder(value)
		sortOrder.value = value
	}

	/**
	 * Update the group mode for the conversation list
	 *
	 * @param value - the group mode ('none', 'group-first', 'private-first')
	 */
	async function updateGroupMode(value: GROUP_MODE_OPTIONS) {
		await setConversationsGroupMode(value)
		groupMode.value = value
	}

	/**
	 * Fetch and store the list of available room presets (only once).
	 */
	async function fetchPresets() {
		if (presets.value.length > 0 || !hasTalkFeature('local', 'conversation-presets')) {
			return
		}
		const response = await getPresets()
		presets.value = response.data.ocs.data
	}

	return {
		readStatusPrivacy,
		typingStatusPrivacy,
		showMediaSettings,
		noiseSuppression,
		noiseSuppressionWithModel,
		echoCancellation,
		autoGainControl,
		startWithoutMedia,
		blurVirtualBackgroundEnabled,
		conversationsListStyle,
		attachmentFolder,
		chatStyle,
		sortOrder,
		groupMode,
		liveTranscriptionTargetLanguageId,
		presets,

		fetchPresets,
		updateSortOrder,
		updateGroupMode,
		updateReadStatusPrivacy,
		updateTypingStatusPrivacy,
		setShowMediaSettings,
		setNoiseSuppression,
		setNoiseSuppressionWithModel,
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
