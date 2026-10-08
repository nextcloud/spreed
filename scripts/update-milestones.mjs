/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Roll the milestones over after a release: the open '<emoji> Next Patch/RC/Major (X)'
 * milestone stays open for upcoming work, while the released work moves to a
 * new 'vX.Y.Z' milestone:
 *   1. Move the current milestone's due date forward (skipped with --last)
 *   2. Create the 'vX.Y.Z' milestone, due today
 *   3. Move completed issues from the current milestone to 'vX.Y.Z'
 *   4. Move merged PRs from the current milestone to 'vX.Y.Z'
 *   5. Clear the milestone of unmerged PRs and not planned / duplicate issues
 *   6. Close the 'vX.Y.Z' milestone
 *   7. With --last only: move open issues to the open '(X+1)' milestone
 *      (warns if none exists), warn about open PRs, close the current milestone
 *
 * New due date: 4 weeks out, or 1 week for a prerelease tag (e.g. -rc.2).
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
 *   npm run release:update-milestones -- <stable-branch> [options]
 *
 * Arguments:
 *   <stable-branch>   The stable branch that was just released, e.g. stable33
 *
 * Options:
 *   --last              Last release of this branch — keep the due date, then retire
 *                       the current milestone: open issues to the next major's '(X+1)'
 *                       milestone, warn about open PRs, close it
 *   -h, --help          Show this help
 */

import process from 'node:process'
import semver from 'semver'
import { branchExists, ghFetchAll, parseArgs, preflight, print, tryRead } from './cli-utils.mjs'
import { fetchOrigin, parseInfoVersion, readBranchInfoVersion, today } from './release-utils.mjs'

const REPO = 'nextcloud/spreed'

/** Print usage information and exit. */
function usage() {
	print.log(`Usage: npm run release:update-milestones -- <STABLE_BRANCH> [OPTIONS]

Roll the milestones over after releasing <STABLE_BRANCH>: move the due date
of the open "Next Patch/RC/Major (X)" milestone matching the Nextcloud stable
branch number forward, create a "vX.Y.Z" milestone (version read from the
branch's appinfo/info.xml), move completed issues and merged PRs to it,
clear the milestone of unmerged PRs and not planned / duplicate issues, then
close "vX.Y.Z".

This never writes to GitHub itself — it prints the exact 'gh' commands for
each step, for you to copy, run and verify.

ARGUMENTS:
    STABLE_BRANCH   The stable branch that was just released, e.g. stable33

OPTIONS:
    --last              Last release of this branch — keep the due date, then retire
                        the current milestone: open issues to the next major's '(X+1)'
                        milestone, warn about open PRs, close it
    -h, --help          Show this help message

EXAMPLES:
    npm run release:update-milestones -- stable33
    npm run release:update-milestones -- stable34 --last
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
 * Compute the current milestone's new due date: 4 weeks out normally, 1 week
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

// Closed issues with these reasons did not ship
const DROPPED_STATE_REASONS = ['not_planned', 'duplicate']

/**
 * Fetch everything on a milestone and split it by state, issue vs PR (a PR is
 * any item carrying a `pull_request` field) and shipped vs dropped (closed
 * unmerged PRs, issues closed as not planned or duplicate).
 *
 * @param {number} milestoneNumber the milestone's number
 * @return {Promise<{closedIssues: Array<object>, mergedPrs: Array<object>, droppedItems: Array<object>, openIssues: Array<object>, openPrs: Array<object>}>} the split items
 */
async function listMilestoneItems(milestoneNumber) {
	const items = await ghFetchAll(`/repos/${REPO}/issues?milestone=${milestoneNumber}&state=all&per_page=100`)
	const closed = items.filter((i) => i.state === 'closed')
	const open = items.filter((i) => i.state === 'open')
	const isDropped = (i) => (i.pull_request ? !i.pull_request.merged_at : DROPPED_STATE_REASONS.includes(i.state_reason))
	return {
		closedIssues: closed.filter((i) => !i.pull_request && !isDropped(i)),
		mergedPrs: closed.filter((i) => i.pull_request && !isDropped(i)),
		droppedItems: closed.filter(isDropped),
		openIssues: open.filter((i) => !i.pull_request),
		openPrs: open.filter((i) => i.pull_request),
	}
}

/**
 * Print a single shell loop that moves every listed issue/PR to a milestone.
 *
 * Always uses `gh issue edit` — PRs are issues under the GitHub API too, and
 * `gh pr edit` errors on this repo with a deprecated Projects-classic
 * GraphQL field (repository.pullRequest.projectCards).
 *
 * @param {Array<{number: number}>} items the issues or PRs to move
 * @param {string} milestoneTitle the destination milestone's title
 */
function printMoveCommand(items, milestoneTitle) {
	const numbers = items.map((i) => i.number).join(' ')
	print.command(`for n in ${numbers}; do gh issue edit "$n" --repo ${REPO} --milestone "${milestoneTitle}"; done`)
}

/**
 * Print a single shell loop that clears the milestone of every listed issue/PR.
 *
 * @param {Array<{number: number}>} items the issues or PRs to clear
 */
function printRemoveMilestoneCommand(items) {
	const numbers = items.map((i) => i.number).join(' ')
	print.command(`for n in ${numbers}; do gh issue edit "$n" --repo ${REPO} --remove-milestone; done`)
}

/**
 * Print the full plan: each checklist step and the `gh` command for it.
 *
 * @param {object} plan the computed plan
 */
function printPlan(plan) {
	const { milestone, releaseTitle, dueDate, last, nextMajorMilestone, nextNcMajor, items } = plan
	const { closedIssues, mergedPrs, droppedItems, openIssues, openPrs } = items

	if (last) {
		print.section(`1. Keep the due date of '${milestone.title}' — --last given`)
	} else {
		print.section(`1. Move the due date of '${milestone.title}' (#${milestone.number}) to ${dueDate.slice(0, 10)}`)
		print.command(`gh api --method PATCH repos/${REPO}/milestones/${milestone.number} -f due_on="${dueDate}"`)
	}

	print.section(`2. Create milestone '${releaseTitle}', due today`)
	print.command(`gh api --method POST repos/${REPO}/milestones -f title="${releaseTitle}" -f due_on="${today()}T00:00:00Z"`)

	print.section(`3. Move completed issues from '${milestone.title}' to '${releaseTitle}'`)
	if (closedIssues.length === 0) {
		print.ok('No completed issues to move')
	} else {
		print.note(`${closedIssues.length} issue(s)`)
		printMoveCommand(closedIssues, releaseTitle)
	}

	print.section(`4. Move merged PRs from '${milestone.title}' to '${releaseTitle}'`)
	if (mergedPrs.length === 0) {
		print.ok('No merged PRs to move')
	} else {
		print.note(`${mergedPrs.length} PR(s)`)
		printMoveCommand(mergedPrs, releaseTitle)
	}

	print.section('5. Clear the milestone of unmerged PRs and not planned / duplicate issues')
	if (droppedItems.length === 0) {
		print.ok('Nothing to clear')
	} else {
		print.note(`${droppedItems.length} item(s)`)
		printRemoveMilestoneCommand(droppedItems)
	}

	print.section(`6. Close milestone '${releaseTitle}'`)
	// Number is only known once step 2 ran, so look it up by title
	print.command(`gh api repos/${REPO}/milestones --paginate --jq '.[] | select(.title == "${releaseTitle}") | .number' | xargs -I{} gh api --method PATCH repos/${REPO}/milestones/{} -f state=closed`)

	if (!last) {
		return
	}

	print.section(`7. Retire '${milestone.title}' (#${milestone.number})`)
	if (openIssues.length === 0) {
		print.ok('No open issues to move')
	} else if (!nextMajorMilestone) {
		print.warn(`No open milestone matching '(${nextNcMajor})' found — ${openIssues.length} issue(s) need manual triage`)
	} else {
		print.note(`${openIssues.length} open issue(s) → '${nextMajorMilestone.title}'`)
		printMoveCommand(openIssues, nextMajorMilestone.title)
	}
	if (openPrs.length > 0) {
		print.warn(`${openPrs.length} open PR(s) not moved — triage manually: ${openPrs.map((i) => `#${i.number}`).join(' ')}`)
	}
	print.command(`gh api --method PATCH repos/${REPO}/milestones/${milestone.number} -f state=closed`)
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

	const milestone = findNextMilestone(ncMajor, milestones)
	if (!milestone) {
		print.err(`No open milestone matching '(${ncMajor})' found — expected e.g. 'Next Patch (${ncMajor})', 'Next RC (${ncMajor})' or 'Next Major (${ncMajor})'`)
		process.exit(1)
	}
	print.note(`Rolling over: '${milestone.title}' (#${milestone.number})`)

	const existingRelease = findMilestoneByTitle(releaseTitle, milestones)
	if (existingRelease) {
		print.err(`Milestone '${releaseTitle}' already exists (#${existingRelease.number})`)
		process.exit(1)
	}

	// With --last, open issues go to the next Nextcloud major's milestone —
	// e.g. rolling over stable33's last release looks for open '(34)'.
	const nextNcMajor = String(Number(ncMajor) + 1)
	const nextMajorMilestone = last ? findNextMilestone(nextNcMajor, milestones) : undefined

	const items = await listMilestoneItems(milestone.number)

	printPlan({ milestone, releaseTitle, dueDate, last, nextMajorMilestone, nextNcMajor, items })
}

main().catch((err) => {
	print.err(err.message)
	process.exit(1)
})
