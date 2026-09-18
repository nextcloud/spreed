/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * The fixed cast every docs screenshot draws from — "test-a1b2c3" and a
 * lorem-ipsum reply read as obviously fake in a screenshot meant for users.
 *
 * Seeded once by global-setup.ts before any test/worker starts; add new
 * names here as more screenshot specs need them.
 */
export const DEMO_PASSWORD = 'demo-password-123'

export const DEMO_USERS = [
	{ userId: 'christine', displayName: 'Christine' },
	{ userId: 'leon', displayName: 'Leon' },
	{ userId: 'louis', displayName: 'Louis' },
] as const
