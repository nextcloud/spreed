/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getLanguage, t } from '@nextcloud/l10n'
import { ATTENDEE } from '../constants.ts'

/**
 * Suffixes and post-nominal credentials that may follow a comma in a display name
 * (e.g. "Martin Luther King, Jr." or "Mary Williams, RN, BSN").
 * Based on https://github.com/joshfraser/JavaScript-Name-Parser
 */
const SUFFIX_PATTERN = new RegExp('^(?:'
	+ 'i{1,3}|iv|v|senior|junior|jr|sr' // generational
	+ '|phd|apr|rph|pe|md|ma|msc|bsc|ba|bs|dmd|cme|bsn|mba' // academic / professional
	+ '|ceo|cto|cfo|coo' // job titles
	+ ')$', 'i')

/**
 * Salutations / honorific prefixes that may precede a given name (e.g. "Dr. Jane Smith")
 */
const SALUTATION_PATTERN = /^(?:mr|mrs|ms|miss|master|mister|dr|rev|fr|prof|herr|frau|mme|mlle|me|pr)$/i

/**
 * Normalizes a word for pattern matching (strips periods and commas, e.g. "Ph.D." => "PhD")
 *
 * @param word single word of a name
 */
function normalizeWord(word: string): string {
	return word.replace(/[.,]/g, '')
}

/**
 * Checks whether a word is a name suffix or post-nominal credential ("Jr.", "MD")
 *
 * @param word single word of a name
 */
function isSuffix(word: string): boolean {
	return SUFFIX_PATTERN.test(normalizeWord(word))
}

/**
 * Checks whether a word is a salutation ("Mr.", "Dr.")
 *
 * @param word single word of a name
 */
function isSalutation(word: string): boolean {
	return SALUTATION_PATTERN.test(normalizeWord(word))
}

/**
 * Checks whether a word is a single-letter initial ("R.", "J", "А.").
 * Restricted to alphabetic scripts: a single CJK character is a complete name component, not an initial
 *
 * @param word single word of a name
 */
function isInitial(word: string): boolean {
	return /^[\p{Script=Latin}\p{Script=Cyrillic}\p{Script=Greek}]$/u.test(normalizeWord(word))
}

/**
 * Returns the first (given) name of a display name.
 *
 * Handles the inverted enterprise-directory convention ("Lastname, Firstname"),
 * comma-separated suffixes and credentials ("Martin Luther King, Jr.", "Jane Smith, MD"),
 * bracketed annotations ("Doe, John (Contracting)", "[Bot] Weather") and salutations ("Prof. Dr. Jane Smith").
 * Falls back to the trimmed input if nothing better can be extracted.
 *
 * @param fullName display name of a user
 */
export function getFirstName(fullName: string): string {
	// Drop bracketed annotations: "Doe, John (Contracting)" => "Doe, John"
	const cleanedName = fullName.replace(/\([^()]*\)|\[[^[\]]*\]|\{[^{}]*\}/g, ' ').trim()

	// Word lists of comma-separated segments: "King, Martin Luther, Jr." => [['King'], ['Martin', 'Luther'], ['Jr.']]
	const segments = cleanedName.split(',')
		.map((segment) => segment.split(/\s+/).filter(Boolean))
		.filter((words) => words.length)

	// Drop trailing segments consisting only of suffixes: [['King'], ['Martin', 'Luther'], ['Jr.']] => [['King'], ['Martin', 'Luther']]
	while (segments.length > 1 && segments.at(-1)!.every(isSuffix)) {
		segments.pop()
	}

	// A remaining comma indicates inverted "Lastname, Firstname" order: [['King'], ['Martin', 'Luther']] => ['Martin', 'Luther']
	let givenWords = (segments.length > 1 ? segments[1] : segments[0]) ?? []

	// Skip leading salutations, also stacked ones: ['Prof.', 'Dr.', 'Jane', 'Smith'] => ['Jane', 'Smith']
	while (givenWords.length > 1 && isSalutation(givenWords[0])) {
		givenWords = givenWords.slice(1)
	}

	// "R. Jason Smith" goes by the middle name, but "R. J. Smith" goes by initials
	const firstName = (givenWords.length > 1 && isInitial(givenWords[0]) && !isInitial(givenWords[1]))
		? givenWords[1]
		: givenWords[0]

	return firstName
		|| cleanedName.split(/\s+/)[0]
		|| fullName.trim()
}

/**
 * Returns display name with 'Guest' or 'Deleted user' fallback if not provided
 *
 * @param displayName possible name of participant
 * @param source actor type of participant
 * @param firstNameOnly whether to return only the first name of display name
 */
export function getDisplayNameWithFallback(displayName: string, source: string, firstNameOnly: boolean = false): string {
	if (displayName?.trim()) {
		return firstNameOnly
			? getFirstName(displayName)
			: displayName.trim()
	}

	if ([ATTENDEE.ACTOR_TYPE.GUESTS, ATTENDEE.ACTOR_TYPE.EMAILS].includes(source)) {
		return t('spreed', 'Guest')
	}

	// Fallback to 'Deleted user':
	// - for matching type: `source === ATTENDEE.ACTOR_TYPE.DELETED_USERS`
	// - in other cases: should not happen, but can not be empty either
	return t('spreed', 'Deleted user')
}

/**
 * Returns concatenated display names with comma divider
 *
 * @param displayNames list of display name
 * @param [maxLength] max allowed length
 */
export function getDisplayNamesList(displayNames: string[], maxLength?: number): string {
	const sanitizedList = displayNames.map((name) => name.trim()).filter(Boolean)

	if (!sanitizedList.length) {
		return ''
	}

	const joinedDisplayNames = new Intl.ListFormat(getLanguage(), {
		style: 'narrow',
		type: 'conjunction',
	}).format(sanitizedList)

	if (maxLength && joinedDisplayNames.length > maxLength) {
		return joinedDisplayNames.substring(0, maxLength - 1) + '…'
	}
	return joinedDisplayNames
}
