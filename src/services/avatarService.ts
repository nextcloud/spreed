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

/**
 *
 * @param token
 * @param isDarkTheme
 * @param avatarVersion
 */
function getConversationAvatarOcsUrl(token: string, isDarkTheme: boolean, avatarVersion?: string): string {
	return generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar' + (isDarkTheme ? '/dark' : '') + (avatarVersion ? '?v={avatarVersion}' : ''), { token, avatarVersion })
}

/**
 *
 * @param token
 * @param cloudId
 * @param isDarkTheme
 * @param size
 */
function getUserProxyAvatarOcsUrl(token: string, cloudId: string, isDarkTheme: boolean, size: 64 | 512 = 512): string {
	return generateOcsUrl('apps/spreed/api/v1/proxy/{token}/user-avatar/{size}' + (isDarkTheme ? '/dark' : '') + '?cloudId={cloudId}', { token, cloudId, size })
}

/**
 *
 * @param token
 * @param file
 */
async function setConversationAvatar(token: string, file: File): setFileAvatarResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar', { token }), file)
}

/**
 *
 * @param token
 * @param emoji
 * @param color
 */
async function setConversationEmojiAvatar(token: string, emoji: string, color?: string | null): setEmojiAvatarResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar/emoji', { token }), {
		emoji,
		color,
	} as setEmojiAvatarParams)
}

/**
 *
 * @param token
 */
async function deleteConversationAvatar(token: string): deleteAvatarResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar', { token }))
}

export {
	deleteConversationAvatar,
	getConversationAvatarOcsUrl,
	getUserProxyAvatarOcsUrl,
	setConversationAvatar,
	setConversationEmojiAvatar,
}
