/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { PRIVACY } from '../constants.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import {
	setAttachmentFolder,
	setBlurVirtualBackground,
	setConversationsListStyle,
	setReadStatusPrivacy,
	setStartWithoutMedia,
	setTypingStatusPrivacy,
} from '../services/settingsService.ts'

type PRIVACY_KEYS = typeof PRIVACY[keyof typeof PRIVACY]
type LIST_STYLE_OPTIONS = 'two-lines' | 'compact'

/**
 * Store for shared items shown in RightSidebar
 */
export const useSettingsStore = defineStore('settings', () => {
	const readStatusPrivacy = ref<PRIVACY_KEYS>(loadState('spreed', 'read_status_privacy', PRIVACY.PRIVATE))
	const typingStatusPrivacy = ref<PRIVACY_KEYS>(loadState('spreed', 'typing_privacy', PRIVACY.PRIVATE))
	const showMediaSettings = ref<boolean>(BrowserStorage.getItem('showMediaSettings') !== 'false')
	const startWithoutMedia = ref<boolean | undefined>(getTalkConfig('local', 'call', 'start-without-media'))
	const blurVirtualBackgroundEnabled = ref<boolean | undefined>(getTalkConfig('local', 'call', 'blur-virtual-background'))
	const conversationsListStyle = ref<LIST_STYLE_OPTIONS | undefined>(getTalkConfig('local', 'conversations', 'list-style'))

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

	return {
		readStatusPrivacy,
		typingStatusPrivacy,
		showMediaSettings,
		startWithoutMedia,
		blurVirtualBackgroundEnabled,
		conversationsListStyle,
		attachmentFolder,
		attachmentFolderFreeSpace,

		updateReadStatusPrivacy,
		updateTypingStatusPrivacy,
		setShowMediaSettings,
		setBlurVirtualBackgroundEnabled,
		updateStartWithoutMedia,
		updateConversationsListStyle,
		updateAttachmentFolder,
	}
})
