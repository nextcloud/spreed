/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Check out a backport changelog PR, bump the version in appinfo/info.xml and
 * package.json, then commit the result.
 *
 * Requirements: git, gh (authenticated), npm.
 *
 * Usage:
 *   node docs/bump-version.mjs <changelog-pr-number>
 *
 * Arguments:
 *   <changelog-pr-number>   GitHub PR number of the backported changelog PR
 *
 * Options:
 *   -h, --help   Show this help
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import process from 'node:process'
import { BLUE, c, NC, run, tryRead } from './cli-utils.mjs'
import { incrementVersion, parseInfoVersion } from './release-utils.mjs'

/** Print usage information and exit. */
function usage() {
	c.log(`Usage: node docs/bump-version.mjs <changelog-pr-number>

Check out the given changelog backport PR, bump the version in
appinfo/info.xml and package.json, then commit the result.

Arguments:
    <changelog-pr-number>   GitHub PR number of the backported changelog PR

Options:
    -h, --help   Show this help message

Example:
    node docs/bump-version.mjs 18500
`)
	process.exit(0)
}

/**
 * Read the app version from appinfo/info.xml.
 *
 * @return {string} the version, or '' when not found
 */
function readInfoVersion() {
	return parseInfoVersion(readFileSync('appinfo/info.xml', 'utf-8'))
}

// --- Argument parsing ------------------------------------------------------
let prNumber = ''

for (const arg of process.argv.slice(2)) {
	if (arg === '-h' || arg === '--help') {
		usage()
	} else if (/^\d+$/.test(arg)) {
		prNumber = arg
	} else {
		c.err(`Unknown argument: ${arg}`)
		usage()
	}
}

if (!prNumber) {
	c.err('Changelog PR number is required')
	usage()
}

// --- Pre-flight ------------------------------------------------------------
let preflightOk = true

for (const bin of ['git', 'gh', 'npm']) {
	if (tryRead(bin, ['--version']) === null) {
		c.err(`Required command '${bin}' not found in PATH.`)
		preflightOk = false
	}
}

if (tryRead('git', ['rev-parse', '--git-dir']) === null) {
	c.err('Not in a git repository')
	preflightOk = false
}

if (!preflightOk) {
	process.exit(1)
}

// --- Check out the PR branch -----------------------------------------------
c.section(`Checking out PR #${prNumber}`)

run('gh', ['pr', 'checkout', prNumber])

const currentBranch = run('git', ['rev-parse', '--abbrev-ref', 'HEAD'], { capture: true })
c.ok(`Now on branch: ${currentBranch}`)

// --- Read and increment version --------------------------------------------
if (!existsSync('appinfo/info.xml')) {
	c.err('appinfo/info.xml not found')
	process.exit(1)
}

const currentVersion = readInfoVersion()
if (!currentVersion) {
	c.err('Could not read version from appinfo/info.xml')
	process.exit(1)
}

const nextVersion = incrementVersion(currentVersion)

c.log()
c.log(`${BLUE}${'━'.repeat(45)}${NC}`)
c.log(`${BLUE}  Bump version: v${currentVersion} → v${nextVersion}${NC}`)
c.log(`${BLUE}  Branch: ${currentBranch}${NC}`)
c.log(`${BLUE}${'━'.repeat(45)}${NC}`)

// --- Bump appinfo/info.xml -------------------------------------------------
c.section('Bumping appinfo/info.xml')

const xml = readFileSync('appinfo/info.xml', 'utf-8')
writeFileSync(
	'appinfo/info.xml',
	xml.replace(`<version>${currentVersion}</version>`, `<version>${nextVersion}</version>`),
)

const verify = readInfoVersion()
if (verify !== nextVersion) {
	c.err(`Version mismatch after edit — expected ${nextVersion}, got ${verify}`)
	process.exit(1)
}
c.ok(`appinfo/info.xml → ${nextVersion}`)

// --- Bump package.json -----------------------------------------------------
c.section('Bumping package.json')

// npm version prints the new version prefixed with 'v', e.g. v25.0.1
const npmOutput = run('npm', ['version', '--no-git-tag-version', nextVersion], { capture: true }).replace(/^v/, '')

if (npmOutput !== nextVersion) {
	c.err(`npm version returned '${npmOutput}', expected '${nextVersion}'`)
	process.exit(1)
}
c.ok(`package.json → ${nextVersion}`)

// --- Commit ----------------------------------------------------------------
c.section('Committing')

run('git', ['add', 'appinfo/info.xml', 'package.json'])
run('git', ['commit', '-s', '-m', `chore(release): Prepare release v${nextVersion}`]) // -s for DCO

c.ok(`Committed: chore(release): Prepare release v${nextVersion}`)

// --- Done — push remains manual --------------------------------------------
c.log()
c.log(`${BLUE}${'━'.repeat(45)}${NC}`)
c.log(`${BLUE}  Done — push when ready:${NC}`)
c.log(`${BLUE}${'━'.repeat(45)}${NC}`)
c.log()
c.log(`  git push origin ${currentBranch}`)
c.log()
