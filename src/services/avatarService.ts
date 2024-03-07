/**
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	deleteAvatarResponse,
	setEmojiAvatarParams,
	setEmojiAvatarResponse,
	setFileAvatarResponse
} from '../types'

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
	} as setEmojiAvatarParams['params'])
}

const deleteConversationAvatar = async function(token: string): deleteAvatarResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar', { token }))
}

export {
	getConversationAvatarOcsUrl,
	getUserProxyAvatarOcsUrl,
	setConversationAvatar,
	setConversationEmojiAvatar,
	deleteConversationAvatar,
}
