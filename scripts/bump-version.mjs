/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Check out a backport changelog PR (or an existing branch), bump the
 * version in appinfo/info.xml and package.json, then commit the result.
 *
 * Requires: git, npm.
 *
 * Usage:
 *   node scripts/bump-version.mjs <changelog-pr-number|branch-name>
 *
 * Arguments:
 *   <changelog-pr-number>   GitHub PR number of the backported changelog PR
 *   <branch-name>           An existing branch to bump directly. Checked out
 *                           locally if present, otherwise fetched and tracked
 *                           from origin. Pulled either way.
 *
 * Options:
 *   -h, --help   Show this help
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import process from 'node:process'
import { branchExists, ghFetch, parseArgs, preflight, print, requireCleanWorktree, run } from './cli-utils.mjs'
import { getCurrentBranch, incrementVersion, readLocalInfoVersion } from './release-utils.mjs'

const REPO = 'nextcloud/spreed'

/** Print usage information and exit. */
function usage() {
	print.log(`Usage: node scripts/bump-version.mjs <changelog-pr-number|branch-name>

Check out the given changelog backport PR (or an existing branch), bump the
version in appinfo/info.xml and package.json, then commit the result.

Arguments:
    <changelog-pr-number>   GitHub PR number of the backported changelog PR
    <branch-name>           An existing branch to bump directly. Checked out
                            locally if present, otherwise fetched and tracked
                            from origin. Pulled either way.

Options:
    -h, --help   Show this help message

Examples:
    node scripts/bump-version.mjs 18500
    node scripts/bump-version.mjs stable33
`)
	process.exit(0)
}

/**
 * Parse CLI arguments.
 *
 * @return {{type: 'pr'|'branch', value: string}} the checkout target
 */
function parseArguments() {
	let target = null

	parseArgs(process.argv.slice(2), {
		usage,
		onPositional: (arg) => {
			target = /^\d+$/.test(arg) ? { type: 'pr', value: arg } : { type: 'branch', value: arg }
		},
	})

	if (!target) {
		print.err('A changelog PR number or branch name is required')
		usage()
	}

	return target
}

/** Check required tools are available; exit if not. */
function checkPreflight() {
	if (!preflight(['git', 'npm'])) {
		process.exit(1)
	}
}

/**
 * Check out the changelog PR branch, via GitHub's refs/pull/<N>/head mirror
 * (works for fork PRs too, no `gh` needed).
 *
 * @param {string} prNumber the changelog PR number
 * @return {Promise<string>} the checked-out branch name
 */
async function checkoutPr(prNumber) {
	print.section(`Checking out PR #${prNumber}`)

	const pr = await ghFetch(`/repos/${REPO}/pulls/${prNumber}`)
	const branchName = pr.head.ref

	run('git', ['fetch', 'origin', `pull/${prNumber}/head`])
	run('git', ['checkout', '-B', branchName, 'FETCH_HEAD'])

	const currentBranch = getCurrentBranch()
	print.ok(`Now on branch: ${currentBranch}`)

	return currentBranch
}

/**
 * Check out the given branch: switch to it if it exists locally, otherwise
 * fetch and track it from origin. Pulls the latest changes either way.
 *
 * @param {string} branchName the branch to check out
 * @return {string} the checked-out branch name
 */
function checkoutBranch(branchName) {
	print.section(`Checking out branch '${branchName}'`)

	const onOrigin = branchExists(`origin/${branchName}`)

	if (branchExists(branchName)) {
		run('git', ['checkout', branchName])
	} else if (onOrigin) {
		run('git', ['fetch', 'origin', branchName])
		run('git', ['checkout', '-b', branchName, `origin/${branchName}`])
	} else {
		print.err(`Branch '${branchName}' not found locally or on origin`)
		process.exit(1)
	}

	// Only pull when there's a remote counterpart to pull from.
	if (onOrigin) {
		run('git', ['pull', '--ff-only', 'origin', branchName])
	}

	const currentBranch = getCurrentBranch()
	print.ok(`Now on branch: ${currentBranch}`)

	return currentBranch
}

/**
 * Read the current version from appinfo/info.xml and compute the next one.
 *
 * @param {string} currentBranch the branch being released, for the report header
 * @return {{currentVersion: string, nextVersion: string}} the current and next version
 */
function readAndIncrementVersion(currentBranch) {
	if (!existsSync('appinfo/info.xml')) {
		print.err('appinfo/info.xml not found')
		process.exit(1)
	}

	const currentVersion = readLocalInfoVersion()
	if (!currentVersion) {
		print.err('Could not read version from appinfo/info.xml')
		process.exit(1)
	}

	const nextVersion = incrementVersion(currentVersion)
	if (nextVersion === null) {
		print.err(`Invalid semantic version in appinfo/info.xml: ${currentVersion}`)
		process.exit(1)
	}

	print.header([`  Bump version: v${currentVersion} → v${nextVersion}`, `  Branch: ${currentBranch}`])

	return { currentVersion, nextVersion }
}

/**
 * Bump the version in appinfo/info.xml and verify the edit landed.
 *
 * @param {string} currentVersion the version currently in the file
 * @param {string} nextVersion the version to write
 */
function bumpInfoXml(currentVersion, nextVersion) {
	print.section('Bumping appinfo/info.xml')

	const xml = readFileSync('appinfo/info.xml', 'utf-8')
	writeFileSync(
		'appinfo/info.xml',
		xml.replace(`<version>${currentVersion}</version>`, `<version>${nextVersion}</version>`),
	)

	const verify = readLocalInfoVersion()
	if (verify !== nextVersion) {
		print.err(`Version mismatch after edit — expected ${nextVersion}, got ${verify}`)
		process.exit(1)
	}
	print.ok(`appinfo/info.xml → ${nextVersion}`)
}

/**
 * Bump the version in package.json via `npm version` and verify its output.
 *
 * @param {string} nextVersion the version to bump to
 */
function bumpPackageJson(nextVersion) {
	print.section('Bumping package.json')

	// npm version prints the new version prefixed with 'v', e.g. v25.0.1
	const npmOutput = run('npm', ['version', '--no-git-tag-version', nextVersion], { capture: true }).replace(/^v/, '')

	if (npmOutput !== nextVersion) {
		print.err(`npm version returned '${npmOutput}', expected '${nextVersion}'`)
		process.exit(1)
	}
	print.ok(`package.json → ${nextVersion}`)
}

/**
 * Commit the version bump.
 *
 * @param {string} nextVersion the version being released, for the commit message
 */
function commitChanges(nextVersion) {
	print.section('Committing')

	run('git', ['add', 'appinfo/info.xml', 'package.json', 'package-lock.json'])
	run('git', ['commit', '-s', '-m', `chore(release): Prepare release v${nextVersion}`]) // -s for DCO

	print.ok(`Committed: chore(release): Prepare release v${nextVersion}`)
}

/**
 * Print the final push instructions. Push itself stays manual.
 *
 * @param {string} currentBranch the branch to push
 */
function printDone(currentBranch) {
	print.header('  Done — push when ready:')
	print.log()
	print.log(`  git push origin ${currentBranch}`)
	print.log()
}

/** Run the version bump. */
async function main() {
	const target = parseArguments()
	checkPreflight()
	requireCleanWorktree()

	const currentBranch = target.type === 'pr' ? await checkoutPr(target.value) : checkoutBranch(target.value)
	const { currentVersion, nextVersion } = readAndIncrementVersion(currentBranch)

	bumpInfoXml(currentVersion, nextVersion)
	bumpPackageJson(nextVersion)
	commitChanges(nextVersion)

	printDone(currentBranch)
}

main().catch((err) => {
	print.err(err.message)
	process.exit(1)
})
