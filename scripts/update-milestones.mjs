/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Roll the milestones over after a release, per the "Rename milestone..."
 * block of the release checklist (https://github.com/nextcloud/spreed/issues/5879):
 *   1. Rename the open '<emoji> Next Patch/RC/Major (X)' milestone to 'vX.Y.Z'
 *   2. Create its follow-up milestone, same emoji (skipped with --last)
 *   3. Move open issues to the follow-up milestone (with --last: to the
 *      open '(X+1)' milestone instead; warns if none exists)
 *   4. Move open PRs to the follow-up milestone (with --last: never moved,
 *      just warns for manual triage)
 *   5. Close the 'vX.Y.Z' milestone
 *
 * Follow-up milestone's flavour/due date derive from the released version:
 * same flavour unless no prerelease tag remains (then "Next Patch"); due in
 * 4 weeks, or 1 week for a prerelease tag (e.g. -rc.2).
 *
 * Version is read from the branch's appinfo/info.xml, same as
 * validate-release.mjs and prepare-changelog.mjs.
 *
 * Read-only against GitHub — every write is a `gh` command printed for a
 * human to run.
 *
 * Requires: git.
 *
 * Usage:
 *   node scripts/update-milestones.mjs <stable-branch> [options]
 *
 * Arguments:
 *   <stable-branch>   The stable branch that was just released, e.g. stable33
 *
 * Options:
 *   --last              Last release of this branch — skip the follow-up milestone;
 *                       plan moving open issues to the next major's '(X+1)' milestone,
 *                       warn about open PRs instead
 *   -h, --help          Show this help
 */

import process from 'node:process'
import semver from 'semver'
import { branchExists, ghFetchAll, parseArgs, preflight, print, tryRead } from './cli-utils.mjs'
import { fetchOrigin, parseInfoVersion, readBranchInfoVersion } from './release-utils.mjs'

const REPO = 'nextcloud/spreed'

/** Print usage information and exit. */
function usage() {
	print.log(`Usage: node scripts/update-milestones.mjs <STABLE_BRANCH> [OPTIONS]

Roll the milestones over after releasing <STABLE_BRANCH>: rename the open
"Next Patch/RC/Major (X)" milestone matching the Nextcloud stable branch
number to "vX.Y.Z" (version read from the branch's appinfo/info.xml), plan
its follow-up milestone (same emoji, flavour and due date derived from the
version) and moving open issues/PRs over, then close "vX.Y.Z".

This never writes to GitHub itself — it prints the exact 'gh' commands for
each step, for you to copy, run and verify.

ARGUMENTS:
    STABLE_BRANCH   The stable branch that was just released, e.g. stable33

OPTIONS:
    --last              Last release of this branch — skip the follow-up milestone;
                        plan moving open issues to the next major's '(X+1)' milestone,
                        warn about open PRs instead
    -h, --help          Show this help message

EXAMPLES:
    node scripts/update-milestones.mjs stable33
    node scripts/update-milestones.mjs stable34 --last
`)
	process.exit(0)
}

/**
 * Parse CLI arguments.
 *
 * @return {{branch: string, last: boolean}} parsed options
 */
function parseArguments() {
	let branch = null
	let last = false

	parseArgs(process.argv.slice(2), {
		usage,
		flags: {
			'--last': () => {
				last = true
			},
		},
		onPositional: (arg) => {
			if (!branch) {
				branch = arg
			}
		},
	})

	if (!branch) {
		print.err('A stable branch is required, e.g. stable33')
		usage()
	}

	return { branch, last }
}

/** Check required tools are available; exit if not. */
function checkPreflight() {
	if (!preflight(['git'])) {
		process.exit(1)
	}
}

/**
 * Resolve the branch's version from appinfo/info.xml: local branch first (no
 * network needed), else fetch and read origin/<branch>.
 *
 * @param {string} branch the stable branch to read
 * @return {string} the version, or '' when it could not be determined
 */
function resolveBranchVersion(branch) {
	if (branchExists(branch)) {
		print.note(`Using local branch '${branch}'`)
		return parseInfoVersion(tryRead('git', ['show', `${branch}:appinfo/info.xml`]))
	}

	fetchOrigin()
	if (!branchExists(`origin/${branch}`)) {
		print.err(`Branch '${branch}' not found locally or on origin`)
		process.exit(1)
	}
	return readBranchInfoVersion(branch)
}

/**
 * Compute the follow-up milestone's due date: 4 weeks out normally, 1 week
 * for a prerelease tag (e.g. 24.0.0-rc.2 — beta/RC cadence).
 *
 * @param {string} version the released version
 * @return {string} an ISO date-time, e.g. '2026-09-28T00:00:00Z'
 */
function computeDueDate(version) {
	const days = semver.prerelease(version) ? 7 : 28
	const date = new Date()
	date.setUTCDate(date.getUTCDate() + days)
	return `${date.toISOString().slice(0, 10)}T00:00:00Z`
}

/**
 * Find a milestone by exact title.
 *
 * @param {string} title the milestone title to look for
 * @param {Array<object>} milestones all milestones
 * @return {object|undefined} the milestone, or undefined when not found
 */
function findMilestoneByTitle(title, milestones) {
	return milestones.find((m) => m.title === title)
}

/**
 * Find the open "next" milestone for a Nextcloud stable branch number,
 * whatever flavour — Next Patch/RC/Major (X). Mirrors prepare-changelog.mjs.
 *
 * @param {string} ncMajor the Nextcloud stable branch number, e.g. '33' for 'stable33'
 * @param {Array<object>} milestones all milestones
 * @return {object|undefined} the milestone, or undefined when not found
 */
function findNextMilestone(ncMajor, milestones) {
	const pattern = new RegExp(`\\(${ncMajor}\\)$`)
	return milestones.find((m) => m.state === 'open' && pattern.test(m.title))
}

/**
 * Derive the follow-up milestone's title: same emoji and flavour, unless the
 * released version has no prerelease tag anymore (branch gone stable), in
 * which case it's always "Next Patch".
 *
 * @param {string} patchTitle the current milestone's title
 * @param {string} version the released version
 * @param {string} ncMajor the Nextcloud stable branch number
 * @return {string} the follow-up milestone's title
 */
function deriveNextPatchTitle(patchTitle, version, ncMajor) {
	const titleMatch = patchTitle.match(/^(\S+)\s+(.+?)\s+\(\d[\d.]*\)$/)
	const emoji = titleMatch?.[1] ?? '💚'
	const flavour = semver.prerelease(version) ? (titleMatch?.[2] ?? 'Next Patch') : 'Next Patch'
	return `${emoji} ${flavour} (${ncMajor})`
}

/**
 * Fetch everything open on a milestone, split into issues and PRs (a PR is
 * any item carrying a `pull_request` field).
 *
 * @param {number} milestoneNumber the milestone's number
 * @return {Promise<{issues: Array<{number: number, title: string}>, prs: Array<{number: number, title: string}>}>} the open issues and PRs
 */
async function listOpenOnMilestone(milestoneNumber) {
	const items = await ghFetchAll(`/repos/${REPO}/issues?milestone=${milestoneNumber}&state=open&per_page=100`)
	return {
		issues: items.filter((i) => !i.pull_request),
		prs: items.filter((i) => i.pull_request),
	}
}

/**
 * Print a single shell loop that moves every listed issue/PR to a milestone.
 *
 * @param {'issue'|'pr'} kind which `gh` subcommand to loop
 * @param {Array<{number: number}>} items the issues or PRs to move
 * @param {string} milestoneTitle the destination milestone's title
 */
function printMoveCommand(kind, items, milestoneTitle) {
	const numbers = items.map((i) => i.number).join(' ')
	print.command(`for n in ${numbers}; do gh ${kind} edit "$n" --repo ${REPO} --milestone "${milestoneTitle}"; done`)
}

/**
 * Print the full plan: each checklist step and the `gh` command for it.
 *
 * @param {object} plan the computed plan
 */
function printPlan(plan) {
	const { patchTitle, patchMilestone, nextPatchTitle, nextMajorMilestone, nextNcMajor, releaseTitle, dueDate, last, openIssues, openPrs } = plan

	print.section(`1. Rename '${patchTitle}' (#${patchMilestone.number}) → '${releaseTitle}'`)
	print.command(`gh api --method PATCH repos/${REPO}/milestones/${patchMilestone.number} -f title="${releaseTitle}"`)

	if (last) {
		print.note("--last given: no follow-up milestone — this branch's line is done")

		print.section(`3. Move open issues from '${releaseTitle}' to the '(${nextNcMajor})' milestone`)
		if (openIssues.length === 0) {
			print.ok('No open issues to move')
		} else if (!nextMajorMilestone) {
			print.warn(`No open milestone matching '(${nextNcMajor})' found — ${openIssues.length} issue(s) need manual triage`)
		} else {
			print.note(`${openIssues.length} issue(s) → '${nextMajorMilestone.title}'`)
			printMoveCommand('issue', openIssues, nextMajorMilestone.title)
		}

		print.section('4. Open PRs')
		if (openPrs.length === 0) {
			print.ok('No open PRs left on this milestone')
		} else {
			print.warn(`${openPrs.length} open PR(s) not moved — triage manually`)
		}
	} else {
		print.section(`2. Create milestone '${nextPatchTitle}', due ${dueDate.slice(0, 10)}`)
		print.command(`gh api --method POST repos/${REPO}/milestones -f title="${nextPatchTitle}" -f due_on="${dueDate}"`)

		print.section(`3. Move open issues from '${releaseTitle}' to '${nextPatchTitle}'`)
		if (openIssues.length === 0) {
			print.ok('No open issues to move')
		} else {
			print.note(`${openIssues.length} issue(s)`)
			printMoveCommand('issue', openIssues, nextPatchTitle)
		}

		print.section(`4. Move open PRs from '${releaseTitle}' to '${nextPatchTitle}'`)
		if (openPrs.length === 0) {
			print.ok('No open PRs to move')
		} else {
			print.note(`${openPrs.length} PR(s)`)
			printMoveCommand('pr', openPrs, nextPatchTitle)
		}
	}

	print.section(`5. Close milestone '${releaseTitle}'`)
	print.command(`gh api --method PATCH repos/${REPO}/milestones/${patchMilestone.number} -f state=closed`)
}

/** Run the milestone rollover plan. */
async function main() {
	const { branch, last } = parseArguments()

	print.header(`Nextcloud Spreed Milestone Rollover — ${branch}`)

	checkPreflight()

	const version = resolveBranchVersion(branch)
	if (!version) {
		print.err(`Could not read version from ${branch}:appinfo/info.xml`)
		process.exit(1)
	}
	print.note(`${branch} is at v${version}`)

	// '(X)' is the Nextcloud stable branch number, not Talk's own version —
	// same convention as prepare-changelog.mjs's ncMajor.
	const ncMajor = (branch.match(/[0-9.]+/) || [''])[0]
	const releaseTitle = `v${version}`
	const dueDate = computeDueDate(version)

	const milestones = await ghFetchAll(`/repos/${REPO}/milestones?state=all&per_page=100`)

	const patchMilestone = findNextMilestone(ncMajor, milestones)
	if (!patchMilestone) {
		print.err(`No open milestone matching '(${ncMajor})' found — expected e.g. 'Next Patch (${ncMajor})', 'Next RC (${ncMajor})' or 'Next Major (${ncMajor})'`)
		process.exit(1)
	}
	const patchTitle = patchMilestone.title
	print.note(`Rolling over: '${patchTitle}' (#${patchMilestone.number})`)

	const nextPatchTitle = deriveNextPatchTitle(patchTitle, version, ncMajor)
	// With --last, open issues go to the next Nextcloud major's milestone —
	// e.g. rolling over stable33's last release looks for open '(34)'.
	const nextNcMajor = String(Number(ncMajor) + 1)
	const nextMajorMilestone = last ? findNextMilestone(nextNcMajor, milestones) : undefined

	const existingRelease = findMilestoneByTitle(releaseTitle, milestones)
	if (existingRelease) {
		print.err(`Milestone '${releaseTitle}' already exists (#${existingRelease.number}) — nothing to rename into`)
		process.exit(1)
	}

	const { issues: openIssues, prs: openPrs } = await listOpenOnMilestone(patchMilestone.number)

	const plan = { patchTitle, patchMilestone, nextPatchTitle, nextMajorMilestone, nextNcMajor, releaseTitle, dueDate, last, openIssues, openPrs }

	printPlan(plan)
}

main().catch((err) => {
	print.err(err.message)
	process.exit(1)
})
