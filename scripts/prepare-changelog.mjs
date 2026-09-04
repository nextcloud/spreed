/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Generate changelog entries for the given stable branches from their merged
 * milestone PRs, and commit them locally — one commit per branch on a new
 * `chore/release/changelog-*` branch (push and PR stay manual).
 *
 * Requires: git.
 *
 * Usage:
 *   node scripts/prepare-changelog.mjs [options] [stable-branch...]
 *
 * Arguments:
 *   stable-branch   Specific stable branches to check (e.g. stable33 stable34).
 *                   Defaults to maintained versions from dependabot.yml.
 *
 * Options:
 *   --dry-run    Preview changelog output without making any changes
 *   -h, --help   Show this help
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import process from 'node:process'
import { branchExists, COLOR, ghFetchAll, parseArgs, preflight, print, requireCleanWorktree, run } from './cli-utils.mjs'
import { detectStableBranches, existingOriginBranches, fetchOrigin, getCurrentBranch, incrementVersion, readBranchInfoVersion, today, todayCompact } from './release-utils.mjs'

const REPO = 'nextcloud/spreed'

/** Print usage information and exit. */
function usage() {
	print.log(`Usage: node scripts/prepare-changelog.mjs [OPTIONS] [STABLE_BRANCH...]

Generate changelog entries for the given stable branches and commit them
locally (push and PR stay manual).

OPTIONS:
    --dry-run    Preview changelog output without making any changes
    -h, --help   Show this help message

ARGUMENTS:
    STABLE_BRANCH   Specific stable branches to check (e.g., stable33, stable34)
                    If not provided, automatically selects maintained versions from dependabot.yml

EXAMPLES:
    node scripts/prepare-changelog.mjs                     # Prepare changelog for maintained stable branches
    node scripts/prepare-changelog.mjs stable33 stable34   # Prepare changelog for specific branches
    node scripts/prepare-changelog.mjs --dry-run           # Preview changelog without making changes
`)
	process.exit(0)
}

/**
 * Parse CLI arguments.
 *
 * @return {{dryRun: boolean, stableBranches: string[]}} parsed options
 */
function parseArguments() {
	let dryRun = false
	const stableBranches = []

	parseArgs(process.argv.slice(2), {
		usage,
		flags: {
			'--dry-run': () => {
				dryRun = true
			},
		},
		onPositional: (arg) => stableBranches.push(arg),
	})

	return {
		dryRun,
		stableBranches: stableBranches.length > 0 ? stableBranches : detectStableBranches(),
	}
}

/** Check git is installed and usable; exit if not. */
function checkPreflight() {
	if (!preflight(['git'])) {
		process.exit(1)
	}
	print.ok('All required tools available')
}

/**
 * Fetch remote info and figure out which branches exist on origin.
 *
 * @param {string[]} stableBranches the branches to check
 * @return {Set<string>} which of those branches exist on origin
 */
function gitSetup(stableBranches) {
	const currentBranch = getCurrentBranch()
	print.note(`Current branch: ${currentBranch}`)

	fetchOrigin()

	return existingOriginBranches(stableBranches)
}

/**
 * Build a formatted changelog section from a milestone's merged PRs.
 *
 * @param {number|string} milestoneNumber the milestone id
 * @param {string} sectionVersion the version the section documents
 * @return {Promise<string>} the markdown changelog section
 */
async function generateChangelogSection(milestoneNumber, sectionVersion) {
	const prData = await ghFetchAll(`/repos/${REPO}/issues?milestone=${milestoneNumber}&state=closed&per_page=100`)

	let hasDeps = false
	const entriesAdded = []
	const entriesFixed = []
	const entriesRemoved = []

	for (const issue of prData) {
		if (!issue.pull_request) {
			continue
		}
		// Strip [stableXX] backport prefix
		const title = issue.title.replace(/^\[stable[0-9.]*\] /, '')

		if (/^(chore|build)\(deps/.test(title)) {
			hasDeps = true
			continue
		}

		const link = `  [#${issue.number}](https://github.com/nextcloud/spreed/pull/${issue.number})`
		const entry = `- ${title}\n${link}`

		if (/^feat/.test(title)) {
			entriesAdded.push(entry)
		} else if (/^fix/.test(title)) {
			entriesFixed.push(entry)
		} else if (/^revert/.test(title)) {
			entriesRemoved.push(entry)
		}
		// docs/ci/chore/perf/refactor/build/test entries are omitted
	}

	const lines = [`## ${sectionVersion} – ${today()}`]

	if (entriesAdded.length > 0) {
		lines.push('### Added')
		lines.push(...entriesAdded)
		lines.push('')
	}

	lines.push('### Changed')
	if (hasDeps) {
		lines.push('- Update dependencies')
	}
	lines.push('- Update translations')
	lines.push('')

	if (entriesFixed.length > 0) {
		lines.push('### Fixed')
		lines.push(...entriesFixed)
		lines.push('')
	}

	if (entriesRemoved.length > 0) {
		lines.push('### Removed')
		lines.push(...entriesRemoved)
		lines.push('')
	}

	return lines.join('\n')
}

/**
 * Insert a changelog section before the first "## " heading, creating the
 * file with a standard header when it does not exist yet.
 *
 * @param {string} file the changelog file path
 * @param {string} content the section to prepend
 */
function prependChangelogSection(file, content) {
	if (!existsSync(file)) {
		// REUSE-IgnoreStart -- another file's SPDX header, not this file's own
		const headerBlock = [
			'<!--',
			`  - SPDX-FileCopyrightText: ${new Date().getFullYear()} Nextcloud GmbH and Nextcloud contributors`,
			'  - SPDX-License-Identifier: CC0-1.0',
			'-->',
			// REUSE-IgnoreEnd
			'# Changelog',
			'All notable changes to this project will be documented in this file.',
			'',
			content,
			'',
		].join('\n')
		writeFileSync(file, headerBlock)
		return
	}

	const fileLines = readFileSync(file, 'utf-8').split('\n')
	const firstSectionIndex = fileLines.findIndex((l) => l.startsWith('## '))

	let result
	if (firstSectionIndex === -1) {
		result = `${fileLines.join('\n')}\n${content}\n`
	} else {
		const before = fileLines.slice(0, firstSectionIndex)
		const after = fileLines.slice(firstSectionIndex)
		result = `${before.join('\n')}\n${content}\n\n${after.join('\n')}`
	}
	writeFileSync(file, result)
}

/**
 * Gather one changelog-ready entry per branch with a version, matching
 * milestone, and generated changelog section.
 *
 * @param {string[]} stableBranches the branches to check
 * @param {Set<string>} existingStableBranches which of those exist on origin
 * @param {Array<object>} milestonesJson all milestones
 * @return {Promise<Array<{branch: string, major: string, nextVersion: string, changelogSection: string}>>} one entry per ready branch
 */
async function gatherChangelogReleases(stableBranches, existingStableBranches, milestonesJson) {
	const releases = []

	for (const branch of stableBranches) {
		if (!existingStableBranches.has(branch)) {
			continue
		}

		const ncMajor = (branch.match(/[0-9.]+/) || [''])[0]
		const branchVersion = readBranchInfoVersion(branch)

		if (!branchVersion) {
			print.warn(`${branch}: could not read version from appinfo/info.xml`)
			continue
		}

		const talkMajor = branchVersion.split('.')[0]

		// Match any "... (version)" milestone (Next Patch/RC/Major)
		const milestoneData = milestonesJson.find((m) => new RegExp(`\\(${ncMajor}\\)`).test(m.title))

		if (!milestoneData) {
			print.warn(`${branch}: no milestone with '(${ncMajor})' found`)
			continue
		}

		const milestoneNumber = milestoneData.number
		const milestoneTitle = milestoneData.title
		const milestoneOpen = milestoneData.open_issues

		const nextVersion = incrementVersion(branchVersion)
		if (nextVersion === null) {
			print.warn(`${branch}: appinfo/info.xml contains invalid semver '${branchVersion}'`)
			continue
		}

		print.item(`${branch}: v${branchVersion} → v${nextVersion} ← ${milestoneTitle} (${milestoneOpen} open issues)`)

		const changelogSection = await generateChangelogSection(milestoneNumber, nextVersion)

		releases.push({ branch, major: talkMajor, nextVersion, changelogSection })
	}

	return releases
}

/**
 * Preview the changelog commits a real run would create, without touching git.
 *
 * @param {string} prBranch the branch name the real run would create
 * @param {Array<{major: string, nextVersion: string, changelogSection: string}>} releases the changelog-ready releases
 */
function previewChangelogCommits(prBranch, releases) {
	print.note(`Branch: ${prBranch} (from main)`)

	for (const { major, nextVersion, changelogSection } of releases) {
		const changelogFile = `docs/changelogs/changelog-${major}.md`
		print.log()
		print.note(`Commit: chore(release): Changelog for v${nextVersion}`)
		print.log(`  ${COLOR.CYAN}--- a/${changelogFile}${COLOR.NC}`)
		print.log(`  ${COLOR.CYAN}+++ b/${changelogFile}${COLOR.NC}`)
		for (const line of changelogSection.split('\n')) {
			print.log(`  ${COLOR.GREEN}+${line}${COLOR.NC}`)
		}
	}
	print.log()
}

/**
 * Create the changelog branch and commit one changelog file per release.
 *
 * @param {string} prBranch the branch name to create
 * @param {Array<{major: string, nextVersion: string, changelogSection: string}>} releases the changelog-ready releases
 * @param {Array<object>} milestonesJson all milestones, for the PR's next-major hint
 * @param {string} versionsStr the joined "vX, vY" versions, for the PR title
 */
function commitChangelogBranch(prBranch, releases, milestonesJson, versionsStr) {
	run('git', ['checkout', '-b', prBranch, 'origin/main'])

	let commitCount = 0

	for (const { major, nextVersion, changelogSection } of releases) {
		const changelogFile = `docs/changelogs/changelog-${major}.md`
		prependChangelogSection(changelogFile, changelogSection)
		run('git', ['add', changelogFile])
		run('git', ['commit', '-s', '-m', `chore(release): Changelog for v${nextVersion}`]) // -s for DCO
		print.ok(`Committed ${changelogFile}`)
		commitCount++
	}

	const nextMajorMilestones = milestonesJson
		.filter((m) => /Next Major/.test(m.title))
		.sort((a, b) => a.title.localeCompare(b.title))
	const nextMajorMilestone = nextMajorMilestones.length > 0
		? nextMajorMilestones[nextMajorMilestones.length - 1].title
		: ''
	const milestoneFlag = nextMajorMilestone ? `--milestone "${nextMajorMilestone}" ` : ''

	print.log()
	print.ok(`Branch '${prBranch}' ready — review and adjust the changelog, then:`)
	print.log(`  git push -u origin ${prBranch}`)
	print.log(`  gh pr create --title "chore(release): Changelog for ${versionsStr}" --base main --assignee @me ${milestoneFlag}--body "$(git diff HEAD~${commitCount}..HEAD -- docs/changelogs/ | grep '^+[^+]' | sed 's/^+//')" --repo nextcloud/spreed`)
}

/** Run changelog generation or its read-only preview. */
async function main() {
	const { dryRun, stableBranches } = parseArguments()

	print.header('Nextcloud Spreed Changelog Preparation')

	checkPreflight()

	if (dryRun) {
		print.log(`${COLOR.YELLOW}[DRY RUN – no changes will be made]${COLOR.NC}`)
	} else {
		requireCleanWorktree()
	}

	if (stableBranches.length === 0) {
		print.err('No stable branches found or given')
		process.exit(1)
	}
	print.log(`Target branches: ${COLOR.BLUE}${stableBranches.join(',')}${COLOR.NC}`)

	const existingStableBranches = gitSetup(stableBranches)

	const milestonesJson = await ghFetchAll(`/repos/${REPO}/milestones?state=open&per_page=100`)

	print.section('Changelog')

	const releases = await gatherChangelogReleases(stableBranches, existingStableBranches, milestonesJson)

	if (releases.length === 0) {
		print.note('Nothing to generate')
		return
	}

	print.section('Preparing Changelog Commits')

	const prBranch = `chore/release/changelog-${todayCompact()}`
	const versionsStr = releases.map((r) => `v${r.nextVersion}`).join(', ')

	if (dryRun) {
		previewChangelogCommits(prBranch, releases)
	} else if (branchExists(prBranch)) {
		print.warn(`Branch '${prBranch}' already exists — delete it first or use a different date suffix`)
	} else {
		commitChangelogBranch(prBranch, releases, milestonesJson, versionsStr)
	}
}

main().catch((err) => {
	print.err(err.message)
	process.exit(1)
})
