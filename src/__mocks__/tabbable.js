/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'

// https://github.com/focus-trap/tabbable#testing-in-jsdom
export default async () => {
	const tabbable = await vi.importActual('tabbable')
	return {
		...tabbable,
		tabbable: vi.fn(),
		focusable: vi.fn(),
		isFocusable: vi.fn(),
		isTabbable: vi.fn(),
	}
}
