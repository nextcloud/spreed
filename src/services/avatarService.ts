/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	deleteAvatarResponse,
	setEmojiAvatarParams,
	setEmojiAvatarResponse,
	setFileAvatarResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const getConversationAvatarOcsUrl = function(token: string, isDarkTheme: boolean, avatarVersion?: string): string {
	return generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar' + (isDarkTheme ? '/dark' : '') + (avatarVersion ? '?v={avatarVersion}' : ''), { token, avatarVersion })
}

const getUserProxyAvatarOcsUrl = function(token: string, cloudId: string, isDarkTheme: boolean, size: 64 | 512 = 512): string {
	return generateOcsUrl('apps/spreed/api/v1/proxy/{token}/user-avatar/{size}' + (isDarkTheme ? '/dark' : '') + '?cloudId={cloudId}', { token, cloudId, size })
}

const setConversationAvatar = async function(token: string, file: File): setFileAvatarResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar', { token }), file)
}

const setConversationEmojiAvatar = async function(token: string, emoji: string, color?: string | null): setEmojiAvatarResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar/emoji', { token }), {
		emoji,
		color,
	} as setEmojiAvatarParams)
}

const deleteConversationAvatar = async function(token: string): deleteAvatarResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar', { token }))
}

export {
	deleteConversationAvatar,
	getConversationAvatarOcsUrl,
	getUserProxyAvatarOcsUrl,
	setConversationAvatar,
	setConversationEmojiAvatar,
}
