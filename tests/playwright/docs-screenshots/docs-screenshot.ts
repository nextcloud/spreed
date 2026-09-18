/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { join } from 'node:path'

// playwright test always runs with the repo root as cwd. Under /test-results,
// already gitignored: the docs site lives in a separate repo, so these are
// picked up and moved over manually rather than committed here.
const OUTPUT_DIR = join(process.cwd(), 'test-results/docs-screenshots-output')

/**
 * Captures `target` (a page or a scoped locator) into the gitignored output
 * folder. Viewport/scale are fixed by playwright-docs.config.ts, the only
 * config this runs under, so every shot comes out the same size.
 *
 * @param target - Page for a full-viewport shot, or a Locator to crop to one element
 * @param name - File name, e.g. "chat.png"
 */
export async function saveDocsScreenshot(target: Page | Locator, name: string): Promise<void> {
	const path = join(OUTPUT_DIR, name)
	await target.screenshot({ path })
	console.log(`[docs-screenshot] wrote ${path}`)
}
