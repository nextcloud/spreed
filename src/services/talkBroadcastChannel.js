/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Broadcast channel to send messages between active tabs.
 */
const talkBroadcastChannel = new BroadcastChannel('nextcloud:talk')

export {
	talkBroadcastChannel,
}
