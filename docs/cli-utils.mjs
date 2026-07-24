/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable no-console */

/**
 * Generic command-line helpers shared by the release scripts: coloured
 * terminal output and fail-fast subprocess runners. Nothing here is specific
 * to the release process.
 */

import { spawnSync } from 'node:child_process'
import process from 'node:process'

// --- Colours ---------------------------------------------------------------
export const RED = '\x1b[0;31m'
export const GREEN = '\x1b[0;32m'
export const YELLOW = '\x1b[1;33m'
export const BLUE = '\x1b[0;34m'
export const CYAN = '\x1b[0;36m'
export const NC = '\x1b[0m'

const BAR = '━'.repeat(45)

/**
 * The single terminal-output surface for the release scripts. Status helpers
 * prefix a coloured symbol; layout helpers frame the report; `log` prints a
 * raw (optionally pre-coloured) line. Every console write goes through here.
 */
export const c = {
	// Status messages
	info: (m) => console.info(`\x1b[1;34m➜\x1b[0m ${m}`),
	ok: (m) => console.info(`\x1b[1;32m✔\x1b[0m ${m}`),
	warn: (m) => console.warn(`\x1b[1;33m!\x1b[0m ${m}`),
	err: (m) => console.error(`\x1b[1;31m✖\x1b[0m ${m}`),

	// Report layout
	header: (m) => {
		console.info('')
		console.info(`${BLUE}${BAR}${NC}`)
		console.info(`${BLUE}${m}${NC}`)
		console.info(`${BLUE}${BAR}${NC}`)
	},
	section: (m) => {
		console.info('')
		console.info(`${CYAN}→ ${m}${NC}`)
	},
	item: (m) => console.info(`  • ${m}`),
	note: (m) => console.info(`  ${m}`),

	// Raw line (default: a blank line)
	log: (m = '') => console.info(m),
}

// --- Subprocess runners ----------------------------------------------------

/**
 * Run a command, inheriting stdio, and exit the process on failure.
 *
 * @param {string} cmd command to run
 * @param {string[]} args arguments
 * @param {object} [options] extra spawn options; `capture` pipes stdout back
 * @return {string} captured stdout (trimmed) when `capture` is set, else ''
 */
export function run(cmd, args, options = {}) {
	const { capture = false, ...rest } = options
	const result = spawnSync(cmd, args, {
		encoding: 'utf-8',
		stdio: capture ? ['inherit', 'pipe', 'inherit'] : 'inherit',
		...rest,
	})
	if (result.status !== 0) {
		c.err(`Command failed: ${cmd} ${args.join(' ')}`)
		process.exit(result.status ?? 1)
	}
	return capture ? (result.stdout ?? '').trim() : ''
}

/**
 * Run a command only to read its output, returning null on failure.
 *
 * @param {string} cmd command to run
 * @param {string[]} args arguments
 * @param {object} [options] extra spawn options
 * @return {string|null} trimmed stdout, or null if the command failed
 */
export function tryRead(cmd, args, options = {}) {
	const result = spawnSync(cmd, args, { encoding: 'utf-8', ...options })
	return result.status === 0 ? (result.stdout ?? '').trim() : null
}
