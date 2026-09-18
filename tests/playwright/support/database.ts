/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runOcc as rawRunOcc } from '@nextcloud/e2e-test-server'
import { createRandomUser as rawCreateRandomUser, login as rawLogin } from '@nextcloud/e2e-test-server/playwright'
import { mkdirSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const LOCK_DIR = join(tmpdir(), 'spreed-playwright-db.lock')
const MAX_WAIT_MS = 30_000

/**
 * Serializes `fn` across every worker process in this test run.
 *
 * Parallel Playwright workers are separate OS processes that can all hit the
 * same throwaway SQLite database at once (user creation, login, room
 * creation). SQLite has no built-in queueing for concurrent writers, and
 * these short-lived connections set no busy timeout, so a collision fails
 * immediately with "database is locked" instead of waiting its turn.
 *
 * Rather than guessing at every error shape that contention can surface as
 * and retrying each one, this makes sure only one such write ever runs at a
 * time — via an atomic `mkdir`, which fails with EEXIST while another
 * worker holds it — without limiting how many workers run in parallel
 * otherwise (page rendering, navigation, etc. are unaffected).
 *
 * @param fn the write operation to serialize
 */
async function withDatabaseLock<T>(fn: () => Promise<T>): Promise<T> {
	const deadline = Date.now() + MAX_WAIT_MS
	for (;;) {
		try {
			mkdirSync(LOCK_DIR)
			break
		} catch {
			if (Date.now() > deadline) {
				throw new Error(`Timed out waiting ${MAX_WAIT_MS}ms for the database lock at ${LOCK_DIR}`)
			}
			await new Promise((resolve) => setTimeout(resolve, 50))
		}
	}
	try {
		return await fn()
	} finally {
		rmSync(LOCK_DIR, { recursive: true, force: true })
	}
}

/**
 * Locked drop-ins for `@nextcloud/e2e-test-server`'s `createRandomUser`,
 * `login`, and `runOcc`. Import these instead of the raw package whenever a
 * fixture or spec writes to the throwaway database — the call is
 * automatically serialized across worker processes, so nobody has to
 * remember to wrap it in a lock themselves.
 *
 * `occ` runs through Docker's exec API and `login()` through an HTTP POST —
 * two unrelated transports, so there is no single network layer to
 * intercept both at. Wrapping the functions here is the equivalent: every
 * call site gets locking for free just by importing from this module.
 *
 * This lock only covers writes made by test-harness code (this module).
 * It was verified — with a throwaway stress spec that has since been
 * removed, sending ~15 rounds of truly concurrent messages between two
 * logged-in pages under 4-worker parallelism — that ordinary in-app chat
 * traffic (message sends, polling, read receipts) does not need the same
 * protection: those writes never collided across several runs, even with
 * SQLite's WAL mode left disabled in start-nextcloud-server.mjs. If that
 * ever changes, re-enable the WAL line there first before reaching for
 * anything more here.
 */
export const createRandomUser: typeof rawCreateRandomUser = () => withDatabaseLock(rawCreateRandomUser)

export const login: typeof rawLogin = (request, user) => withDatabaseLock(() => rawLogin(request, user))

export const runOcc: typeof rawRunOcc = (command, options) => withDatabaseLock(() => rawRunOcc(command, options))
