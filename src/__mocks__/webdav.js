/**
 * SPDX-FileCopyrightText: Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// eslint-disable-next-line n/no-unpublished-import
import { createClient } from 'webdav'

jest.mock('webdav', () => ({
	createClient: jest.fn(),
}))

export { createClient }
