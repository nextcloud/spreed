/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Pre-release preparation report for Nextcloud Spreed. Gathers pending
 * backports, milestone status, open PRs and dependabot coverage for the
 * stable branches. Read-only — see prepare-changelog.mjs to generate and
 * commit changelog entries.
 *
 * Requires: git.
 *
 * Usage:
 *   node scripts/validate-release.mjs [options] [stable-branch...]
 *
 * Arguments:
 *   stable-branch   Specific stable branches to check (e.g. stable33 stable34).
 *                   Defaults to maintained versions from dependabot.yml.
 *
 * Options:
 *   --verbose    Show detailed output
 *   -h, --help   Show this help
 */

import { existsSync, readFileSync } from 'node:fs'
import process from 'node:process'
import { COLOR, ghFetchAll, parseArgs, preflight, print, run, tryRead } from './cli-utils.mjs'
import { detectStableBranches, existingOriginBranches, fetchOrigin, getCurrentBranch, readBranchInfoVersion, readLocalInfoVersion } from './release-utils.mjs'

const REPO = 'nextcloud/spreed'

/** Print usage information and exit. */
function usage() {
	print.log(`Usage: node scripts/validate-release.mjs [OPTIONS] [STABLE_BRANCH...]

Gather release preparation information for Nextcloud Spreed

Run from 'main' branch to report on upcoming releases.

OPTIONS:
    --verbose      Show detailed output
    -h, --help     Show this help message

ARGUMENTS:
    STABLE_BRANCH   Specific stable branches to check (e.g., stable33, stable34)
                    If not provided, automatically selects maintained versions from dependabot.yml

EXAMPLES:
    node scripts/validate-release.mjs                     # Check maintained stable branches
    node scripts/validate-release.mjs stable33 stable34   # Check specific branches
    node scripts/validate-release.mjs --verbose            # Show every open issue, not just high-priority
`)
	process.exit(0)
}

/**
 * Parse CLI arguments.
 *
 * @return {{verbose: boolean, stableBranches: string[]}} parsed options
 */
function parseArguments() {
	let verbose = false
	const stableBranches = []

	parseArgs(process.argv.slice(2), {
		usage,
		flags: {
			'--verbose': () => {
				verbose = true
			},
		},
		onPositional: (arg) => stableBranches.push(arg),
	})

	return {
		verbose,
		stableBranches: stableBranches.length > 0 ? stableBranches : detectStableBranches(),
	}
}

/** Check git is installed and usable; exit if not. */
function checkPreflight() {
	const ok = preflight(['git'])

	if (!ok) {
		process.exit(1)
	}

	print.ok('All required tools available')
}

/**
 * Print the target scope.
 *
 * @param {string[]} stableBranches the branches being checked
 */
function printScope(stableBranches) {
	if (stableBranches.length === 0) {
		print.log(`Scope: ${COLOR.BLUE}main branch (preparation only)${COLOR.NC}`)
	} else {
		print.log(`Target branches: ${COLOR.BLUE}${stableBranches.join(',')}${COLOR.NC}`)
	}
}

/**
 * Fetch remote info and figure out which branches exist on origin.
 *
 * @param {string[]} stableBranches the branches to check
 * @return {{currentBranch: string, existingStableBranches: Set<string>}} git state
 */
function gitSetup(stableBranches) {
	const currentBranch = getCurrentBranch()
	print.note(`Current branch: ${currentBranch}`)

	fetchOrigin()

	// Checked once, reused below instead of re-running `git rev-parse` per section.
	const existingStableBranches = existingOriginBranches(stableBranches)

	return { currentBranch, existingStableBranches }
}

/** Report the version in appinfo/info.xml and package.json, warning on mismatch. */
function reportVersionInfo() {
	print.section('Version Information')

	const version = readLocalInfoVersion()
	if (version) {
		print.note(`appinfo/info.xml: ${version}`)
	}

	let pkgVersion = ''
	if (existsSync('package.json')) {
		try {
			pkgVersion = JSON.parse(readFileSync('package.json', 'utf-8')).version || ''
		} catch {
			pkgVersion = ''
		}
		if (pkgVersion) {
			print.note(`package.json:     ${pkgVersion}`)
		}
	}

	if (version && pkgVersion && version !== pkgVersion) {
		print.warn('Version mismatch between appinfo/info.xml and package.json')
	}
}

/**
 * Fetch all open PRs once, filtered client-side by sections 1 and 3. Uses
 * the Pull Requests API (not Issues), since that's what carries `.base.ref`.
 *
 * @return {Promise<Array<object>>} the open PRs
 */
function fetchOpenPrs() {
	return ghFetchAll(`/repos/${REPO}/pulls?state=open&per_page=100`)
}

/**
 * Section 1 — report open PRs labelled as pending backports.
 *
 * @param {Array<object>} openPrs all open PRs
 */
function reportPendingBackports(openPrs) {
	print.section('Pending Backports')

	const backports = openPrs.filter((pr) => (pr.labels || []).some((l) => l.name === 'backport-request'))

	if (backports.length === 0) {
		print.ok('No pending backports')
	} else {
		print.warn(`${backports.length} pending backport(s):`)
		for (const pr of backports) {
			print.log(`  • #${pr.number} [${pr.base.ref}]: ${pr.title}`)
		}
	}
}

/**
 * Section 2 — report open milestones and their high-priority issues.
 *
 * @param {boolean} verbose whether to list every open issue, not just high-priority
 * @return {Promise<Array<object>>} the open milestones, reused by later sections
 */
async function reportMilestonesStatus(verbose) {
	print.section('Milestones Status')

	const openMilestones = await ghFetchAll(`/repos/${REPO}/milestones?state=open&per_page=100`)

	if (openMilestones.length === 0) {
		print.note('No open milestones found')
		return openMilestones
	}

	for (const milestone of openMilestones) {
		const title = milestone.title
		// Milestone already carries the open-issue count; only fetch the
		// issue list when there's actually something open.
		const openIssues = milestone.open_issues

		if (openIssues === 0) {
			print.item(`${title} (ready)`)
			continue
		}

		// Issues API mixes in PRs; filter those out below.
		let path = `/repos/${REPO}/issues?milestone=${milestone.number}&state=open&per_page=100`
		if (!verbose) {
			// Only high-priority ones get printed, so filter server-side.
			path += '&labels=high'
		}
		const issues = (await ghFetchAll(path)).filter((i) => !i.pull_request)
		const highIssues = verbose ? issues.filter((i) => (i.labels || []).some((l) => l.name === 'high')) : issues

		if (highIssues.length > 0) {
			print.item(`${title}: ${COLOR.YELLOW}${openIssues} open issue(s)${COLOR.NC} (${COLOR.RED}${highIssues.length} high-priority${COLOR.NC})`)
			for (const i of highIssues) {
				print.log(`    ${COLOR.RED}#${i.number}: ${i.title}${COLOR.NC}`)
			}
		} else {
			print.item(`${title}: ${COLOR.YELLOW}${openIssues} open issue(s)${COLOR.NC}`)
		}
		if (verbose) {
			for (const i of issues) {
				print.log(`#${i.number}: ${i.title}`)
			}
		}
	}

	return openMilestones
}

/**
 * Section 3 — report open PRs per stable branch, reusing section 1's list.
 *
 * @param {string[]} stableBranches the branches to report on
 * @param {Set<string>} existingStableBranches which of those exist on origin
 * @param {Array<object>} openPrs all open PRs
 */
function reportOpenPullRequests(stableBranches, existingStableBranches, openPrs) {
	print.section('Open Pull Requests')

	if (stableBranches.length === 0) {
		print.note('No stable branches to check')
		return
	}

	for (const branch of stableBranches) {
		if (!existingStableBranches.has(branch)) {
			print.warn(`Branch '${branch}' not found in origin`)
			continue
		}

		const prs = openPrs.filter((pr) => pr.base.ref === branch)
		if (prs.length === 0) {
			print.item(`${branch}: no open PRs`)
		} else {
			print.item(`${branch}: ${COLOR.YELLOW}${prs.length} open PR(s)${COLOR.NC}`)
			for (const pr of prs) {
				print.log(`      #${pr.number}: ${pr.title}`)
			}
		}
	}
}

/**
 * Section 4 — report whether each stable branch has dependabot patch-update
 * coverage.
 *
 * @param {string[]} stableBranches the branches to report on
 * @return {string|null} the raw .github/dependabot.yml content, or null if missing
 */
function reportDependabotCoverage(stableBranches) {
	print.section('Dependabot Coverage')

	const dependabotContent = existsSync('.github/dependabot.yml') ? readFileSync('.github/dependabot.yml', 'utf-8') : null

	if (stableBranches.length === 0) {
		print.note('No stable branches to check')
	} else if (dependabotContent === null) {
		print.warn('.github/dependabot.yml not found')
	} else {
		for (const branch of stableBranches) {
			if (dependabotContent.includes(`target-branch: ${branch}`)) {
				print.ok(`${branch}: patch updates configured`)
			} else {
				print.warn(`${branch}: missing from .github/dependabot.yml — add composer and npm patch update entries`)
			}
		}
	}

	return dependabotContent
}

/**
 * Section 5 — checks for a branch preparing the first RC of a major release
 * (minor=0, patch=0, no RC tags yet): manual checklist items plus
 * dependabot/migration-diff status.
 *
 * @param {string} branch the stable branch to check
 * @param {string|null} dependabotContent the raw .github/dependabot.yml content
 * @param {() => void} announceSection prints the section header, once, right before the first qualifying branch
 */
function checkFirstRcOfMajorRelease(branch, dependabotContent, announceSection) {
	const branchVersion = readBranchInfoVersion(branch)
	if (!branchVersion) {
		return
	}

	const [talkMajor, talkMinor, patchRaw] = branchVersion.split('.')
	const talkPatch = patchRaw?.match(/^\d+/)?.[0] ?? ''

	if (talkMinor !== '0' || talkPatch !== '0') {
		return
	}

	const rcTags = tryRead('git', ['ls-remote', '--tags', 'origin', `refs/tags/v${talkMajor}.0.0-rc.*`]) || ''
	const existingRcs = rcTags.split('\n').filter(Boolean).length
	if (existingRcs > 0) {
		return
	}

	announceSection()
	print.item(`${branch} at v${branchVersion} — preparing first RC of Talk ${talkMajor}`)

	print.warn(`  Manual: Create 'New in Talk ${talkMajor}' entries in the 'Talk updates ✅' conversation`)
	print.warn('  Manual: Review GDPR document for any new database tables/columns')
	print.note('  Hint:   Run \'make appstore\' to verify packaging exclude list in Makefile is up to date')

	// Dependabot check for this branch (template item: "patch updates to the stable branch")
	if (dependabotContent !== null) {
		if (dependabotContent.includes(`target-branch: ${branch}`)) {
			print.ok(`  dependabot.yml: patch updates configured for ${branch}`)
		} else {
			print.warn(`  dependabot.yml: ${branch} is missing — add composer and npm patch update entries`)
		}
	}

	// New DB migrations since last tag (to assist the GDPR check)
	const lastTag = tryRead('git', ['describe', '--tags', '--abbrev=0', `origin/${branch}`])
	if (lastTag) {
		const diff = tryRead('git', ['diff', '--name-only', `${lastTag}..origin/${branch}`, '--', 'lib/Migration/']) || ''
		const newMigrations = diff.split('\n').filter((f) => f.endsWith('.php'))
		if (newMigrations.length === 0) {
			print.ok(`  No new DB migration files since ${lastTag}`)
		} else {
			print.warn(`  New DB migration files since ${lastTag} (verify GDPR document):`)
			for (const f of newMigrations) {
				print.log(`      • ${f}`)
			}
		}
	} else {
		print.note('  No previous tag found — check DB migrations manually')
	}
}

/**
 * Section 5 — run the first-RC checks for every branch, printing the section
 * header only once and only if a branch triggers it.
 *
 * @param {string[]} stableBranches the branches to check
 * @param {Set<string>} existingStableBranches which of those exist on origin
 * @param {string|null} dependabotContent the raw .github/dependabot.yml content
 */
function reportFirstRcChecks(stableBranches, existingStableBranches, dependabotContent) {
	// Fires when minor=0, patch=0, and no RC tags exist yet — the prep phase
	// before rc.1 is tagged.
	let firstRcFound = false
	const announceSection = () => {
		if (!firstRcFound) {
			print.section('First RC of Major Release — Additional Checks')
			firstRcFound = true
		}
	}

	for (const branch of stableBranches) {
		if (!existingStableBranches.has(branch)) {
			continue
		}
		checkFirstRcOfMajorRelease(branch, dependabotContent, announceSection)
	}
}

/** Section 7 — warn about any uncommitted local changes. */
function reportRepositoryStatus() {
	print.section('Repository Status')

	const status = run('git', ['status', '--porcelain'], { capture: true })
	if (status) {
		print.warn('Uncommitted changes detected:')
		for (const line of status.split('\n')) {
			print.log(`    ${line}`)
		}
	} else {
		print.ok('Working directory is clean')
	}
}

/** Print the follow-up steps once the report is done. */
function printNextSteps() {
	print.header('Next Steps')

	print.log()
	print.log(`${COLOR.CYAN}Address any blockers above, then:${COLOR.NC}`)
	print.log('  1. Prepare changelog:  make prepare-changelog')
	print.log('     Review and adjust docs/changelogs/*.md, then push and open a PR')
	print.log('  2. Follow https://github.com/nextcloud/spreed/issues/5879 template')
}

/** Run the release-preparation report. */
async function main() {
	const { verbose, stableBranches } = parseArguments()

	print.header('Nextcloud Spreed Release Preparation Report')

	checkPreflight()
	printScope(stableBranches)

	const { existingStableBranches } = gitSetup(stableBranches)

	reportVersionInfo()

	const openPrs = await fetchOpenPrs()
	reportPendingBackports(openPrs)

	await reportMilestonesStatus(verbose)
	reportOpenPullRequests(stableBranches, existingStableBranches, openPrs)
	const dependabotContent = reportDependabotCoverage(stableBranches)
	reportFirstRcChecks(stableBranches, existingStableBranches, dependabotContent)

	reportRepositoryStatus()
	printNextSteps()
}

main().catch((err) => {
	print.err(err.message)
	process.exit(1)
})
