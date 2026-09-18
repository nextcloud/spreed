/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

/**
 * Hits Talk's room OCS endpoints directly via `page.request`, instead of
 * through src/services/conversationsService.ts / avatarService.ts: those run
 * in-browser and depend on @nextcloud/axios (CSRF token) and
 * @nextcloud/router's generateOcsUrl() (OC.* globals) — neither exists in
 * this Node-side test harness. Each helper below mirrors one of those
 * service functions' endpoint/params so the two stay in lockstep; see each
 * helper's docstring for which one.
 */

/**
 * Shape of the `TalkRoom` fields these helpers' callers care about — see
 * lib/ResponseDefinitions.php.
 */
export interface RoomAvatarResult {
	avatarVersion: string
	isCustomAvatar: boolean
}

export interface RoomNameResult {
	name: string
}

export interface RoomDescriptionResult {
	description: string
}

export interface CreatedRoom {
	token: string
	name: string
}

async function requestRoom<T>(page: Page, method: 'post' | 'put', path: string, data: Record<string, unknown>): Promise<T> {
	// baseURL ends in "/index.php/"; OCS lives one level up from there.
	const response = await page.request[method](`../ocs/v2.php/apps/spreed/api/${path}?format=json`, {
		// Lets a session-cookie request skip the CSRF token these endpoints
		// would otherwise need.
		headers: { 'OCS-APIRequest': 'true' },
		data,
	})
	const { ocs } = await response.json()
	return ocs.data
}

/**
 * Creates a group conversation via Talk's room-creation OCS endpoint, for
 * tests that exercise the API surface itself rather than going through
 * `occ talk:room:create` (see conversations.ts's `createConversation`).
 * `page` must belong to a logged-in user, who becomes the room's owner.
 *
 * Mirrors src/services/conversationsService.ts's `createLegacyConversation`
 * (same endpoint/params) — that one runs in-browser via axios, which is why
 * it can't be imported directly here; see this file's module comment.
 */
export async function createRoomViaApi(page: Page, name: string): Promise<CreatedRoom> {
	return requestRoom(page, 'post', 'v4/room', { roomType: 2 /* Room::TYPE_GROUP */, roomName: name })
}

/**
 * Sets a conversation's avatar to an emoji on a color, via Talk's
 * `avatar/emoji` OCS endpoint — no file upload needed. `page` must belong to
 * a moderator of `token`'s room.
 *
 * Mirrors src/services/avatarService.ts's `setConversationEmojiAvatar`.
 */
export async function setRoomEmojiAvatar(page: Page, token: string, emoji: string, color?: string): Promise<RoomAvatarResult> {
	return requestRoom(page, 'post', `v1/room/${token}/avatar/emoji`, { emoji, color })
}

/**
 * Renames a conversation, via Talk's room-rename OCS endpoint. `page` must
 * belong to a moderator of `token`'s room.
 *
 * Mirrors src/services/conversationsService.ts's `setConversationName`.
 */
export async function setRoomName(page: Page, token: string, name: string): Promise<RoomNameResult> {
	return requestRoom(page, 'put', `v4/room/${token}`, { roomName: name })
}

/**
 * Sets a conversation's description, via Talk's set-description OCS
 * endpoint. `page` must belong to a moderator of `token`'s room.
 *
 * Mirrors src/services/conversationsService.ts's `setConversationDescription`.
 */
export async function setRoomDescription(page: Page, token: string, description: string): Promise<RoomDescriptionResult> {
	return requestRoom(page, 'put', `v4/room/${token}/description`, { description })
}
