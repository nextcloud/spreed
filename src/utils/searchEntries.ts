/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	UnifiedSearchResultEntry,
	UnifiedSearchResultEntryWithRouterLink,
} from '../types/index.ts'

/**
 * Extend a raw unified search entry with a router link to the message
 *
 * @param entry the unified search result entry
 */
export function mapMessageResultEntry(entry: UnifiedSearchResultEntry): UnifiedSearchResultEntryWithRouterLink {
	const threadId = entry.attributes.threadId !== entry.attributes.messageId
		? entry.attributes.threadId
		: undefined

	return {
		...entry,
		to: {
			name: 'conversation',
			hash: `#message_${entry.attributes.messageId}`,
			params: { token: entry.attributes.conversation },
			query: { threadId },
		},
	}
}
