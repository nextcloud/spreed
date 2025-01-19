/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

enum POLL_STATUS {
	OPEN = 0,
	CLOSED = 1,
	DRAFT = 2,
}
enum POLL_MODE {
	PUBLIC = 0,
	HIDDEN = 1,
}
enum POLL_ANSWER_TYPE {
	MULTIPLE = 0,
	SINGLE = 1,
}
export const POLL = {
	STATUS: POLL_STATUS,
	MODE: POLL_MODE,
	ANSWER_TYPE: POLL_ANSWER_TYPE,
}
