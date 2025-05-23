/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createClient } from 'webdav'

jest.mock('webdav', () => ({
	createClient: jest.fn(),
}))

export { createClient }
