/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Release-specific helpers shared by the release scripts: version parsing,
 * bumping, date formatting, stable-branch detection and shared git state.
 */

import { existsSync, readFileSync } from 'node:fs'
import semver from 'semver'
import { branchExists, print, run, tryRead } from './cli-utils.mjs'

/**
 * Extract the app version from an appinfo/info.xml string.
 *
 * @param {string|null} xml the file contents
 * @return {string} the version, or '' when not found
 */
export function parseInfoVersion(xml) {
	if (!xml) {
		return ''
	}
	const match = xml.match(/<version>([^<]*)<\/version>/)
	return match ? match[1] : ''
}

/**
 * Read and parse the version from a local appinfo/info.xml.
 *
 * @param {string} [path] path to the info.xml file
 * @return {string} the version, or '' when the file is missing or has no version
 */
export function readLocalInfoVersion(path = 'appinfo/info.xml') {
	return existsSync(path) ? parseInfoVersion(readFileSync(path, 'utf-8')) : ''
}

/**
 * Increment a version string: the prerelease number for an RC, otherwise the patch.
 * e.g. 24.0.0-rc.3 → 24.0.0-rc.4   |   23.0.5 → 23.0.6
 *
 * @param {string} version the version to bump
 * @return {string|null} the incremented version, or null when not valid semver
 */
export function incrementVersion(version) {
	if (!semver.valid(version)) {
		return null
	}
	const type = semver.prerelease(version) ? 'prerelease' : 'patch'
	return semver.inc(version, type)
}

/**
 * Today's date as YYYY-MM-DD.
 *
 * @return {string} the ISO date
 */
export function today() {
	return new Date().toISOString().slice(0, 10)
}

/**
 * Today's date as YYYYMMDD.
 *
 * @return {string} the compact date
 */
export function todayCompact() {
	return today().replace(/-/g, '')
}

/**
 * Descending "version sort" for stableNN branch names (mimics `sort -Vr`).
 *
 * @param {string} a first branch name
 * @param {string} b second branch name
 * @return {number} comparison result
 */
function compareVersionsDesc(a, b) {
	return semver.rcompare(semver.coerce(a), semver.coerce(b))
}

/**
 * Auto-detect maintained stable branches from dependabot.yml, falling back
 * to the top 3 remote stable branches.
 *
 * @return {string[]} the detected branch names, highest version first
 */
export function detectStableBranches() {
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
		return [...found].sort(compareVersionsDesc)
	}

	const remote = tryRead('git', ['branch', '-r']) || ''
	const found = new Set()
	for (const line of remote.split('\n')) {
		const m = line.match(/origin\/(stable[0-9.]+)/)
		if (m) {
			found.add(m[1])
		}
	}
	return [...found].sort(compareVersionsDesc).slice(0, 3)
}

/**
 * The current branch name.
 *
 * @return {string} the current branch name
 */
export function getCurrentBranch() {
	return run('git', ['rev-parse', '--abbrev-ref', 'HEAD'], { capture: true })
}

/** Fetch from origin, warning (not failing) when it doesn't work. */
export function fetchOrigin() {
	print.note('Fetching remote info...')
	if (tryRead('git', ['fetch', 'origin', '--quiet']) === null) {
		print.warn('Could not fetch from origin')
	}
}

/**
 * Which of the given branches exist on origin.
 *
 * @param {string[]} branches branch names to check
 * @return {Set<string>} the subset that exist as origin/<branch>
 */
export function existingOriginBranches(branches) {
	return new Set(branches.filter((b) => branchExists(`origin/${b}`)))
}

/**
 * Read and parse the appinfo/info.xml version from a remote branch.
 *
 * @param {string} branch the branch to read from
 * @return {string} the version, or '' when not found
 */
export function readBranchInfoVersion(branch) {
	return parseInfoVersion(tryRead('git', ['show', `origin/${branch}:appinfo/info.xml`]))
}
