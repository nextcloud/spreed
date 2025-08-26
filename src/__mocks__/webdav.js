/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import { createClient } from 'webdav'

vi.mock('webdav', () => ({
	createClient: vi.fn(),
}))

export { createClient }
