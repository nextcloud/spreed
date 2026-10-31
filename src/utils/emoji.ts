/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emojiGetShortName } from '@nextcloud/vue/functions/emoji'

/**
 * Get a hover title for a native emoji, e.g. ':sweat_smile:' for 😅
 *
 * @param emoji The native emoji
 * @return The title, or undefined if the emoji is unknown
 */
export function getEmojiTitle(emoji: string): string | undefined {
	const shortName = emojiGetShortName(emoji)
	return shortName ? `:${shortName}:` : undefined
}
