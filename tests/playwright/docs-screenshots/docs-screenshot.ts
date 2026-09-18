/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { readFile } from 'node:fs/promises'
import { join } from 'node:path'

// playwright test always runs with the repo root as cwd. Under /test-results,
// already gitignored: the docs site lives in a separate repo, so these are
// picked up and moved over manually rather than committed here.
const OUTPUT_DIR = join(process.cwd(), 'test-results/docs-screenshots-output')
const AVATARS_DIR = join(process.cwd(), 'tests/playwright/docs-screenshots/demo-avatars')

/**
 * Uploads `demo-avatars/<userId>.png` as the account's personal avatar.
 *
 * `OCS-APIRequest: true` skips the CSRF token core's avatar endpoint would
 * otherwise require for a session-cookie request — same trick Talk's own
 * OCS-based APIs get for free by extending OCSController.
 */
export async function setDemoAvatar(page: Page, userId: string): Promise<void> {
	const buffer = await readFile(join(AVATARS_DIR, `${userId}.png`))
	await page.request.post('./avatar/', {
		headers: { 'OCS-APIRequest': 'true' },
		multipart: { 'files[]': { name: `${userId}.png`, mimeType: 'image/png', buffer } },
	})
}

/**
 * Captures `target` (a page or a scoped locator) into the gitignored output
 * folder. Viewport/scale are fixed by the `docs` project, the only
 * project this runs under, so every shot comes out the same size.
 *
 * @param target - Page for a full-viewport shot, or a Locator to crop to one element
 * @param name - File name, e.g. "chat.png"
 */
export async function saveDocsScreenshot(target: Page | Locator, name: string): Promise<void> {
	const path = join(OUTPUT_DIR, name)
	await target.screenshot({ path })
	console.log(`[docs-screenshot] wrote ${path}`)
}
