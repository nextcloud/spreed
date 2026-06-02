/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Pre-release preparation report for Nextcloud Spreed. Gathers pending
 * backports, milestone status, open PRs and dependabot coverage for the stable
 * branches, and optionally generates changelog entries as local commits (push
 * and PR stay manual).
 *
 * Requirements: git, gh (authenticated).
 *
 * Usage:
 *   node docs/validate-release.mjs [options] [stable-branch...]
 *
 * Arguments:
 *   stable-branch   Specific stable branches to check (e.g. stable33 stable34).
 *                   Defaults to the 3 highest versions.
 *
 * Options:
 *   --prepare-changelog   Generate changelog entries and commit locally
 *   --dry-run             Preview changelog output without changes (implies
 *                         --prepare-changelog)
 *   --verbose             Show detailed output
 *   -h, --help            Show this help
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import process from 'node:process'
import { BLUE, c, CYAN, GREEN, NC, RED, run, tryRead, YELLOW } from './cli-utils.mjs'
import { incrementVersion, parseInfoVersion, today, todayCompact } from './release-utils.mjs'

/** Print usage information and exit. */
function usage() {
	c.log(`Usage: node docs/validate-release.mjs [OPTIONS] [STABLE_BRANCH...]

Gather release preparation information for Nextcloud Spreed

Run from 'main' branch to report on upcoming releases.

OPTIONS:
    --prepare-changelog   Generate changelog entries and commit locally (push and PR are manual)
    --dry-run               Preview changelog output without making any git/PR changes
                            (implies --prepare-changelog)
    --verbose               Show detailed output
    -h, --help              Show this help message

ARGUMENTS:
    STABLE_BRANCH   Specific stable branches to check (e.g., stable33, stable34)
                    If not provided, automatically selects the 3 highest versions

EXAMPLES:
    node docs/validate-release.mjs                     # Check top 3 stable branches
    node docs/validate-release.mjs stable33 stable34   # Check specific branches
    node docs/validate-release.mjs --dry-run           # Preview changelog without making changes
    node docs/validate-release.mjs --prepare-changelog # Generate changelog and open a PR
`)
	process.exit(0)
}

/**
 * Whether a git ref resolves (branch, tag or HEAD).
 *
 * @param {string} branch the ref to check
 * @return {boolean} true when the ref exists
 */
function branchExists(branch) {
	return tryRead('git', ['rev-parse', '--verify', branch]) !== null
}

/**
 * Descending "version sort" for stableNN branch names (mimics `sort -Vr`).
 *
 * @param {string} a first branch name
 * @param {string} b second branch name
 * @return {number} comparison result
 */
function compareVersionsDesc(a, b) {
	const na = (a.match(/[0-9.]+/) || ['0'])[0].split('.').map(Number)
	const nb = (b.match(/[0-9.]+/) || ['0'])[0].split('.').map(Number)
	for (let i = 0; i < Math.max(na.length, nb.length); i++) {
		const diff = (nb[i] || 0) - (na[i] || 0)
		if (diff !== 0) {
			return diff
		}
	}
	return 0
}

/**
 * Run a `gh` command and parse its JSON output, returning a fallback on failure.
 *
 * @param {string[]} args arguments passed to gh
 * @param {*} fallback value returned when the command fails or output is invalid
 * @return {*} the parsed JSON, or the fallback
 */
function ghJson(args, fallback) {
	const out = tryRead('gh', args)
	if (out === null) {
		return fallback
	}
	try {
		return JSON.parse(out)
	} catch {
		return fallback
	}
}

// --- Argument parsing ------------------------------------------------------
let dryRun = false
let verbose = false
let prepareChangelog = false
let stableBranches = []

for (const arg of process.argv.slice(2)) {
	switch (arg) {
		case '--dry-run':
			dryRun = true
			prepareChangelog = true
			break
		case '--verbose':
			verbose = true
			break
		case '--prepare-changelog':
			prepareChangelog = true
			break
		case '-h':
		case '--help':
			usage()
			break
		default:
			stableBranches.push(arg)
	}
}

if (stableBranches.length === 0) {
	if (existsSync('.github/dependabot.yml')) {
		const content = readFileSync('.github/dependabot.yml', 'utf-8')
		const found = new Set()
		for (const line of content.split('\n')) {
			if (line.includes('target-branch:')) {
				const m = line.match(/stable[0-9.]+/)
				if (m) {
					found.add(m[0])
				}
			}
		}
		stableBranches = [...found].sort(compareVersionsDesc)
	} else {
		const remote = tryRead('git', ['branch', '-r']) || ''
		const found = new Set()
		for (const line of remote.split('\n')) {
			const m = line.match(/origin\/(stable[0-9.]+)/)
			if (m) {
				found.add(m[1])
			}
		}
		stableBranches = [...found].sort(compareVersionsDesc).slice(0, 3)
	}
}

c.header('Nextcloud Spreed Release Preparation Report')

// --- Pre-flight ------------------------------------------------------------
let preflightOk = true

if (tryRead('git', ['--version']) === null) {
	c.err('git is not installed')
	preflightOk = false
} else if (tryRead('git', ['rev-parse', '--git-dir']) === null) {
	c.err('Not in a git repository')
	preflightOk = false
}

if (tryRead('gh', ['--version']) === null) {
	c.err('GitHub CLI (gh) is not installed — https://github.com/cli/cli#installation')
	preflightOk = false
} else if (tryRead('gh', ['auth', 'status']) === null) {
	c.err('GitHub CLI (gh) is not authenticated — run: gh auth login')
	preflightOk = false
}

if (!preflightOk) {
	process.exit(1)
}

c.ok('All required tools available')

if (dryRun) {
	c.log(`${YELLOW}[DRY RUN – no changes will be made]${NC}`)
}

if (stableBranches.length === 0) {
	c.log(`Scope: ${BLUE}main branch (preparation only)${NC}`)
} else {
	c.log(`Target branches: ${BLUE}${stableBranches.join(',')}${NC}`)
}

// --- Git setup -------------------------------------------------------------
const currentBranch = run('git', ['rev-parse', '--abbrev-ref', 'HEAD'], { capture: true })
c.note(`Current branch: ${currentBranch}`)

c.note('Fetching remote info...')
if (tryRead('git', ['fetch', 'origin', '--quiet']) === null) {
	c.warn('Could not fetch from origin')
}

// --- Version info ----------------------------------------------------------
c.section('Version Information')

let version = ''
let pkgVersion = ''

if (existsSync('appinfo/info.xml')) {
	version = parseInfoVersion(readFileSync('appinfo/info.xml', 'utf-8'))
	if (version) {
		c.note(`appinfo/info.xml: ${version}`)
	}
}

if (existsSync('package.json')) {
	try {
		pkgVersion = JSON.parse(readFileSync('package.json', 'utf-8')).version || ''
	} catch {
		pkgVersion = ''
	}
	if (pkgVersion) {
		c.note(`package.json:     ${pkgVersion}`)
	}
}

if (version && pkgVersion && version !== pkgVersion) {
	c.warn('Version mismatch between appinfo/info.xml and package.json')
}

// --- 1. Pending backports --------------------------------------------------
c.section('Pending Backports')

const backports = ghJson(
	['pr', 'list', '--label', 'backport-request', '--state', 'open', '--repo', 'nextcloud/spreed', '--json', 'number,title,baseRefName'],
	[],
)

if (backports.length === 0) {
	c.ok('No pending backports')
} else {
	c.warn(`${backports.length} pending backport(s):`)
	for (const pr of backports) {
		c.log(`  • #${pr.number} [${pr.baseRefName}]: ${pr.title}`)
	}
}

// --- 2. Milestones status --------------------------------------------------
c.section('Milestones Status')

const milestonesJson = ghJson(['api', 'repos/nextcloud/spreed/milestones'], [])
const openMilestones = milestonesJson.filter((m) => m.state === 'open')

if (openMilestones.length === 0) {
	c.note('No open milestones found')
} else {
	for (const milestone of openMilestones) {
		const title = milestone.title
		// One call per milestone: fetch all open issues with labels, filter client-side
		const issues = ghJson(
			['issue', 'list', '--milestone', title, '--state', 'open', '--repo', 'nextcloud/spreed', '--json', 'number,title,labels'],
			[],
		)

		const openIssues = issues.length
		const highIssues = issues.filter((i) => (i.labels || []).some((l) => l.name === 'high'))

		if (openIssues === 0) {
			c.item(`${title} (ready)`)
		} else if (highIssues.length > 0) {
			c.item(`${title}: ${YELLOW}${openIssues} open issue(s)${NC} (${RED}${highIssues.length} high-priority${NC})`)
			for (const i of highIssues) {
				c.log(`    ${RED}#${i.number}: ${i.title}${NC}`)
			}
		} else {
			c.item(`${title}: ${YELLOW}${openIssues} open issue(s)${NC}`)
			if (verbose) {
				for (const i of issues) {
					c.log(`#${i.number}: ${i.title}`)
				}
			}
		}
	}
}

// --- 3. Open pull requests against stable branches -------------------------
c.section('Open Pull Requests')

if (stableBranches.length === 0) {
	c.note('No stable branches to check')
} else {
	for (const branch of stableBranches) {
		if (branchExists(`origin/${branch}`)) {
			const prs = ghJson(
				['pr', 'list', '--base', branch, '--state', 'open', '--repo', 'nextcloud/spreed', '--json', 'number,title'],
				[],
			)
			if (prs.length === 0) {
				c.item(`${branch}: no open PRs`)
			} else {
				c.item(`${branch}: ${YELLOW}${prs.length} open PR(s)${NC}`)
				for (const pr of prs) {
					c.log(`      #${pr.number}: ${pr.title}`)
				}
			}
		} else {
			c.warn(`Branch '${branch}' not found in origin`)
		}
	}
}

// --- 4. Dependabot coverage ------------------------------------------------
c.section('Dependabot Coverage')

let dependabotContent = null
if (existsSync('.github/dependabot.yml')) {
	dependabotContent = readFileSync('.github/dependabot.yml', 'utf-8')
}

if (stableBranches.length === 0) {
	c.note('No stable branches to check')
} else if (dependabotContent === null) {
	c.warn('.github/dependabot.yml not found')
} else {
	for (const branch of stableBranches) {
		if (dependabotContent.includes(`target-branch: ${branch}`)) {
			c.ok(`${branch}: patch updates configured`)
		} else {
			c.warn(`${branch}: missing from .github/dependabot.yml — add composer and npm patch update entries`)
		}
	}
}

// --- 5. First RC of major release checks (conditional per branch) ----------
// Fires when: minor=0 and patch=0 (major release series) and no RC tags exist yet.
// This catches the preparation phase before rc.1 is tagged, not just when already at rc.1.
let firstRcFound = false

if (stableBranches.length > 0) {
	for (const branch of stableBranches) {
		if (!branchExists(`origin/${branch}`)) {
			continue
		}

		const branchXml = tryRead('git', ['show', `origin/${branch}:appinfo/info.xml`])
		const branchVersion = parseInfoVersion(branchXml)
		if (!branchVersion) {
			continue
		}

		const [talkMajor, talkMinor, patchRaw] = branchVersion.split('.')
		const talkPatch = (patchRaw && patchRaw.match(/^\d+/)) ? patchRaw.match(/^\d+/)[0] : ''

		if (talkMinor !== '0' || talkPatch !== '0') {
			continue
		}

		const rcTags = tryRead('git', ['ls-remote', '--tags', 'origin', `refs/tags/v${talkMajor}.0.0-rc.*`]) || ''
		const existingRcs = rcTags ? rcTags.split('\n').filter(Boolean).length : 0
		if (existingRcs > 0) {
			continue
		}

		if (!firstRcFound) {
			c.section('First RC of Major Release — Additional Checks')
			firstRcFound = true
		}

		c.item(`${branch} at v${branchVersion} — preparing first RC of Talk ${talkMajor}`)

		c.warn(`  Manual: Create 'New in Talk ${talkMajor}' entries in the 'Talk updates ✅' conversation`)
		c.warn('  Manual: Review GDPR document for any new database tables/columns')
		c.note('  Hint:   Run \'make appstore\' to verify packaging exclude list in Makefile is up to date')

		// Dependabot check for this branch (template item: "patch updates to the stable branch")
		if (dependabotContent !== null) {
			if (dependabotContent.includes(`target-branch: ${branch}`)) {
				c.ok(`  dependabot.yml: patch updates configured for ${branch}`)
			} else {
				c.warn(`  dependabot.yml: ${branch} is missing — add composer and npm patch update entries`)
			}
		}

		// New DB migrations since last tag (to assist the GDPR check)
		const lastTag = tryRead('git', ['describe', '--tags', '--abbrev=0', `origin/${branch}`])
		if (lastTag) {
			const diff = tryRead('git', ['diff', '--name-only', `${lastTag}..origin/${branch}`, '--', 'lib/Migration/']) || ''
			const newMigrations = diff.split('\n').filter((f) => f.endsWith('.php'))
			if (newMigrations.length === 0) {
				c.ok(`  No new DB migration files since ${lastTag}`)
			} else {
				c.warn(`  New DB migration files since ${lastTag} (verify GDPR document):`)
				for (const f of newMigrations) {
					c.log(`      • ${f}`)
				}
			}
		} else {
			c.note('  No previous tag found — check DB migrations manually')
		}
	}
}

// --- 6. Changelog preparation (only when --prepare-changelog is passed) -----

/**
 * Build a formatted changelog section from a milestone's merged PRs.
 *
 * @param {number|string} milestoneNumber the milestone id
 * @param {string} sectionVersion the version the section documents
 * @return {string} the markdown changelog section
 */
function generateChangelogSection(milestoneNumber, sectionVersion) {
	const prData = ghJson(
		['api', '--paginate', `repos/nextcloud/spreed/issues?milestone=${milestoneNumber}&state=closed&per_page=100`],
		[],
	)

	let hasDeps = false
	let hasL10n = false
	const entriesAdded = []
	const entriesFixed = []
	const entriesRemoved = []

	for (const issue of prData) {
		if (!issue.pull_request) {
			continue
		}
		// Strip [stableXX] backport prefix so entries read cleanly
		const title = issue.title.replace(/^\[stable[0-9.]*\] /, '')

		if (/^(chore|build)\(deps/.test(title)) {
			hasDeps = true
			continue
		}
		if (/^(chore|fix)\(l10n/i.test(title)) {
			hasL10n = true
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
		// docs, ci, chore, perf, refactor, build, test entries are intentionally omitted
	}

	const lines = [`## ${sectionVersion} – ${today()}`]

	if (entriesAdded.length > 0) {
		lines.push('### Added')
		lines.push(...entriesAdded)
		lines.push('')
	}

	if (hasDeps || hasL10n) {
		lines.push('### Changed')
		if (hasDeps) {
			lines.push('- Update dependencies')
		}
		if (hasL10n) {
			lines.push('- Update translations')
		}
		lines.push('')
	}

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
 * Insert a changelog section before the first "## " heading in a file,
 * creating the file with a standard header when it does not exist yet.
 *
 * @param {string} file the changelog file path
 * @param {string} content the section to prepend
 */
function prependChangelogSection(file, content) {
	if (!existsSync(file)) {
		const headerBlock = [
			'<!--',
			`  - SPDX-FileCopyrightText: ${new Date().getFullYear()} Nextcloud GmbH and Nextcloud contributors`,
			'  - SPDX-License-Identifier: CC0-1.0',
			'-->',
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
		result = `${before.join('\n')}${content}\n\n${after.join('\n')}`
	}
	writeFileSync(file, result)
}

if (prepareChangelog) {
	c.section('Changelog')

	if (stableBranches.length === 0) {
		c.note('No stable branches — nothing to generate')
	} else {
		const changelogVersions = []
		const branchMajors = {}
		const branchNextVersions = {}
		const branchSections = {}

		for (const branch of stableBranches) {
			if (!branchExists(`origin/${branch}`)) {
				continue
			}

			const ncMajor = (branch.match(/[0-9.]+/) || [''])[0]
			const branchVersion = parseInfoVersion(tryRead('git', ['show', `origin/${branch}:appinfo/info.xml`]))

			if (!branchVersion) {
				c.warn(`${branch}: could not read version from appinfo/info.xml`)
				continue
			}

			const talkMajor = branchVersion.split('.')[0]

			const milestoneData = milestonesJson.find((m) => new RegExp(`Next Patch \\(${ncMajor}\\)`).test(m.title))

			if (!milestoneData) {
				c.warn(`${branch}: no 'Next Patch (${ncMajor})' milestone found`)
				continue
			}

			const milestoneNumber = milestoneData.number
			const milestoneTitle = milestoneData.title
			const milestoneOpen = milestoneData.open_issues

			const nextVersion = incrementVersion(branchVersion)

			c.item(`${branch}: v${branchVersion} → v${nextVersion} ← ${milestoneTitle} (${milestoneOpen} open issues)`)

			const changelogSection = generateChangelogSection(milestoneNumber, nextVersion)

			changelogVersions.push(`v${nextVersion}`)
			branchMajors[branch] = talkMajor
			branchNextVersions[branch] = nextVersion
			branchSections[branch] = changelogSection
		}

		if (changelogVersions.length > 0) {
			c.section('Preparing Changelog Commits')

			const prBranch = `chore/release/changelog-${todayCompact()}`
			const versionsStr = changelogVersions.join(', ')

			if (dryRun) {
				c.note(`Branch: ${prBranch} (from main)`)

				for (const branch of stableBranches) {
					const major = branchMajors[branch]
					const nextVersion = branchNextVersions[branch]
					const changelogSection = branchSections[branch]
					if (!major || !changelogSection) {
						continue
					}

					const changelogFile = `docs/changelogs/changelog-${major}.md`
					c.log()
					c.note(`Commit: chore(release): Changelog for v${nextVersion}`)
					c.log(`  ${CYAN}--- a/${changelogFile}${NC}`)
					c.log(`  ${CYAN}+++ b/${changelogFile}${NC}`)
					for (const line of changelogSection.split('\n')) {
						c.log(`  ${GREEN}+${line}${NC}`)
					}
				}
				c.log()
			} else if (branchExists(prBranch)) {
				c.warn(`Branch '${prBranch}' already exists — delete it first or use a different date suffix`)
			} else {
				run('git', ['checkout', '-b', prBranch, 'origin/main'])

				let commitCount = 0

				for (const branch of stableBranches) {
					const major = branchMajors[branch]
					const nextVersion = branchNextVersions[branch]
					const changelogSection = branchSections[branch]
					if (!major || !changelogSection) {
						continue
					}

					const changelogFile = `docs/changelogs/changelog-${major}.md`
					prependChangelogSection(changelogFile, changelogSection)
					run('git', ['add', changelogFile])
					run('git', ['commit', '-s', '-m', `chore(release): Changelog for v${nextVersion}`]) // -s for DCO
					c.ok(`Committed ${changelogFile}`)
					commitCount++
				}

				const nextMajorMilestones = milestonesJson
					.filter((m) => /Next Major/.test(m.title))
					.sort((a, b) => a.title.localeCompare(b.title))
				const nextMajorMilestone = nextMajorMilestones.length > 0
					? nextMajorMilestones[nextMajorMilestones.length - 1].title
					: ''
				const milestoneFlag = nextMajorMilestone ? `--milestone "${nextMajorMilestone}" ` : ''

				c.log()
				c.ok(`Branch '${prBranch}' ready — review and adjust the changelog, then:`)
				c.log(`  git push -u origin ${prBranch}`)
				c.log(`  gh pr create --title "chore(release): Changelog for ${versionsStr}" --base main --assignee @me ${milestoneFlag}--body "$(git diff HEAD~${commitCount}..HEAD -- docs/changelogs/ | grep '^+[^+]' | sed 's/^+//')" --repo nextcloud/spreed`)
			}
		}
	}
}

// --- 7. Repository status --------------------------------------------------
c.section('Repository Status')

const status = run('git', ['status', '--porcelain'], { capture: true })
if (status) {
	c.warn('Uncommitted changes detected:')
	const short = run('git', ['status', '--short'], { capture: true })
	for (const line of short.split('\n')) {
		c.log(`    ${line}`)
	}
} else {
	c.ok('Working directory is clean')
}

// --- Next steps ------------------------------------------------------------
c.header('Next Steps')

c.log()
c.log(`${CYAN}Address any blockers above, then:${NC}`)
c.log('  1. Prepare changelog:  make prepare-changelog')
c.log('     Review and adjust docs/changelogs/*.md, then push and open a PR')
c.log('  2. Follow https://github.com/nextcloud/spreed/issues/5879 template')
