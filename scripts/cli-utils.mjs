/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Generic CLI helpers shared by the release scripts: coloured terminal
 * output, fail-fast subprocess runners, and read-only GitHub REST API
 * access. Writes are never made here, only printed via print.command.
 */

import { spawnSync } from 'node:child_process'
import process from 'node:process'

// --- Colours ---------------------------------------------------------------
export const COLOR = {
	RED: '\x1b[0;31m',
	GREEN: '\x1b[0;32m',
	YELLOW: '\x1b[1;33m',
	BLUE: '\x1b[0;34m',
	CYAN: '\x1b[0;36m',
	WHITE: '\x1b[0;37m',
	NC: '\x1b[0m',
}

const BAR = '━'.repeat(45)

/** The single terminal-output surface for the release scripts. */
export const print = {
	// Status messages
	info: (message) => console.info(`${COLOR.BLUE}➜${COLOR.NC} ${message}`),
	ok: (message) => console.info(`${COLOR.GREEN}✔${COLOR.NC} ${message}`),
	warn: (message) => console.warn(`${COLOR.YELLOW}!${COLOR.NC} ${message}`),
	err: (message) => console.error(`${COLOR.RED}✖${COLOR.NC} ${message}`),

	// Report layout. `message` may be a single line or an array of lines.
	header: (message) => {
		console.info('')
		console.info(`${COLOR.BLUE}${BAR}${COLOR.NC}`)
		for (const line of Array.isArray(message) ? message : [message]) {
			console.info(`${COLOR.BLUE}${line}${COLOR.NC}`)
		}
		console.info(`${COLOR.BLUE}${BAR}${COLOR.NC}`)
	},
	section: (message) => {
		console.info('')
		console.info(`${COLOR.CYAN}→ ${message}${COLOR.NC}`)
	},
	item: (message) => console.info(`  • ${message}`),
	note: (message) => console.info(`  ${message}`),

	// A `gh` command line for the reader to copy, run and verify themselves.
	command: (cmd) => console.info(`  ${COLOR.WHITE}$ ${cmd}${COLOR.NC}`),

	// Raw line (default: a blank line)
	log: (message = '') => console.info(message),
}

// --- Subprocess runners ----------------------------------------------------

/**
 * Spawn a command synchronously and return its raw result.
 *
 * @param {string} cmd command to run
 * @param {string[]} args arguments
 * @param {object} [options] extra spawn options
 * @return {import('node:child_process').SpawnSyncReturns<string>} the result
 */
function spawn(cmd, args, options = {}) {
	return spawnSync(cmd, args, { encoding: 'utf-8', ...options })
}

/**
 * Run a command, inheriting stdio, and exit the process on failure.
 *
 * @param {string} cmd command to run
 * @param {string[]} args arguments
 * @param {object} [options] extra spawn options; `capture` pipes stdout back
 * @return {string} trimmed stdout when `capture` is set, else ''
 */
export function run(cmd, args, options = {}) {
	const { capture = false, ...rest } = options
	const result = spawn(cmd, args, { stdio: capture ? ['inherit', 'pipe', 'inherit'] : 'inherit', ...rest })
	if (result.status !== 0) {
		print.err(`Command failed: ${cmd} ${args.join(' ')}`)
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
	const result = spawn(cmd, args, options)
	return result.status === 0 ? (result.stdout ?? '').trim() : null
}

// --- GitHub REST API (read-only) --------------------------------------------
//
// nextcloud/spreed is public: plain `fetch` against api.github.com, no auth
// needed. GH_TOKEN/GITHUB_TOKEN, if set, raises the rate limit to 5,000/hr.

const GITHUB_API = 'https://api.github.com'

/**
 * The token the user explicitly opted into, if any — GH_TOKEN or GITHUB_TOKEN.
 *
 * @return {string|null} the token, or null when neither var is set
 */
function getAuthToken() {
	return process.env.GH_TOKEN || process.env.GITHUB_TOKEN || null
}

/**
 * Headers for a GitHub REST API request, with an Authorization header added
 * when a token is available.
 *
 * @return {Record<string, string>} the headers
 */
function githubApiHeaders() {
	const headers = { Accept: 'application/vnd.github+json', 'X-GitHub-Api-Version': '2022-11-28' }
	const token = getAuthToken()
	if (token) {
		headers.Authorization = `Bearer ${token}`
	}
	return headers
}

/**
 * GET one page from the GitHub REST API and parse it as JSON.
 *
 * @param {string} path an API path, e.g. '/repos/nextcloud/spreed/pulls/123'
 * @return {Promise<*>} the parsed JSON body
 */
export async function ghFetch(path) {
	const url = `${GITHUB_API}${path}`
	const res = await fetch(url, { headers: githubApiHeaders() })
	if (!res.ok) {
		print.err(`GitHub API request failed: ${res.status} ${res.statusText} — ${url}`)
		process.exit(1)
	}
	return res.json()
}

/**
 * GET every page of a paginated GitHub REST API listing, following the
 * response's `Link` header, and return the concatenated results.
 *
 * @param {string} path an API path; include `per_page=100` for fewer round-trips
 * @return {Promise<Array<*>>} all items across every page
 */
export async function ghFetchAll(path) {
	let url = `${GITHUB_API}${path}`
	const results = []
	while (url) {
		const res = await fetch(url, { headers: githubApiHeaders() })
		if (!res.ok) {
			print.err(`GitHub API request failed: ${res.status} ${res.statusText} — ${url}`)
			process.exit(1)
		}
		results.push(...(await res.json()))

		const link = res.headers.get('link') || ''
		const next = link.split(',').find((part) => part.includes('rel="next"'))
		url = next ? next.split(';')[0].trim().slice(1, -1) : null
	}
	return results
}

/**
 * Whether a git ref resolves (branch, tag or HEAD).
 *
 * @param {string} ref the ref to check
 * @return {boolean} true when the ref exists
 */
export function branchExists(ref) {
	return tryRead('git', ['rev-parse', '--verify', ref]) !== null
}

/** Exit if the repository has staged, unstaged, or untracked changes. */
export function requireCleanWorktree() {
	const status = run('git', ['status', '--porcelain'], { capture: true })
	if (status) {
		print.err('Working directory is not clean; commit, stash, or remove local changes first')
		process.exit(1)
	}
}

// Binaries with a more specific "not found" message than the generic one below.
const MISSING_BIN_MESSAGES = {
	git: 'git is not installed',
}

/**
 * Check that required binaries are on PATH. When 'git' is listed, also
 * requires the current directory to be a git repository.
 *
 * @param {string[]} bins required binaries, checked via `<bin> --version`
 * @return {boolean} true when every check passed
 */
export function preflight(bins) {
	let ok = true
	const passed = new Set()
	for (const bin of bins) {
		if (tryRead(bin, ['--version']) === null) {
			print.err(MISSING_BIN_MESSAGES[bin] ?? `Required command '${bin}' not found in PATH.`)
			ok = false
		} else {
			passed.add(bin)
		}
	}
	if (passed.has('git') && tryRead('git', ['rev-parse', '--git-dir']) === null) {
		print.err('Not in a git repository')
		ok = false
	}
	return ok
}

/**
 * Parse `process.argv.slice(2)`-style arguments against a set of flags, with
 * unmatched arguments passed to `onPositional`. `-h`/`--help` calls `usage`.
 *
 * @param {string[]} argv arguments to parse
 * @param {object} spec parsing spec
 * @param {Record<string, () => void>} spec.flags map of flag name to handler
 * @param {(arg: string) => void} spec.onPositional called for non-flag arguments
 * @param {() => void} spec.usage prints usage and exits; invoked on -h/--help or an unknown flag
 */
export function parseArgs(argv, { flags = {}, onPositional, usage }) {
	for (const arg of argv) {
		if (arg === '-h' || arg === '--help') {
			usage()
		} else if (arg in flags) {
			flags[arg]()
		} else if (arg.startsWith('-')) {
			print.err(`Unknown argument: ${arg}`)
			usage()
		} else {
			onPositional(arg)
		}
	}
}
