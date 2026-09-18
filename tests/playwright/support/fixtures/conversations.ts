/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runOcc } from '../database.ts'
import { test as base } from './users.ts'

/**
 * Mirrors every option `occ talk:room:create` accepts, besides the required
 * `name`. See `lib/Command/Room/Create.php`.
 */
export interface CreateConversationOptions {
	description?: string
	user?: string[]
	group?: string[]
	public?: boolean
	readonly?: boolean
	listable?: number
	password?: string
	owner?: string
	moderator?: string[]
	messageExpiration?: number
	preserve?: boolean
}

function buildCreateArgs(name: string, options: CreateConversationOptions): string[] {
	const args = ['talk:room:create']

	if (options.description !== undefined) {
		args.push('--description', options.description)
	}
	for (const user of options.user ?? []) {
		args.push('--user', user)
	}
	for (const group of options.group ?? []) {
		args.push('--group', group)
	}
	if (options.public) {
		args.push('--public')
	}
	if (options.readonly) {
		args.push('--readonly')
	}
	if (options.listable !== undefined) {
		args.push('--listable', String(options.listable))
	}
	if (options.password !== undefined) {
		args.push('--password', options.password)
	}
	if (options.owner !== undefined) {
		args.push('--owner', options.owner)
	}
	for (const moderator of options.moderator ?? []) {
		args.push('--moderator', moderator)
	}
	if (options.messageExpiration !== undefined) {
		args.push('--message-expiration', String(options.messageExpiration))
	}
	if (options.preserve) {
		args.push('--preserve')
	}

	// Everything after "--" is positional, so a name starting with "-" is
	// never misread as another option.
	args.push('--', name)
	return args
}

/**
 * Test fixture exposing a `createConversation()` factory: creates a Talk
 * conversation via `occ talk:room:create` and returns its room token.
 */
export const test = base.extend<{
	createConversation: (name: string, options?: CreateConversationOptions) => Promise<string>
}>({
	// Playwright reads this literal object pattern to know this fixture needs no
	// other fixtures; anything else (e.g. a plain `_`) breaks its dependency
	// detection at runtime.
	// eslint-disable-next-line no-empty-pattern
	createConversation: async ({}, use) => {
		await use(async (name, options = {}) => {
			const { stdout } = await runOcc(buildCreateArgs(name, options), { verbose: true })

			const token = stdout.match(/Room token: (\S+)/)?.[1]
			if (!token) {
				throw new Error(`Could not read the room token from occ output:\n${stdout}`)
			}
			return token
		})
	},
})
