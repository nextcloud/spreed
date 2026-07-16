/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { describe, expect, it } from 'vitest'
import { getFirstName } from '../getDisplayName.ts'

describe('getDisplayName', () => {
	describe('getFirstName', () => {
		const TEST_CASES = [
			// Natural order
			['Hermes Conrad', 'Hermes'],
			['Hermes', 'Hermes'],
			['  Hermes   Conrad  ', 'Hermes'],
			['Philip J. Fry', 'Philip'],
			// Inverted "Lastname, Firstname" order
			['Conrad, Hermes', 'Hermes'],
			['Smith, John Jacob', 'John'],
			['van der Berg, Anna', 'Anna'],
			['Doe, John (Contracting)', 'John'],
			// Suffixes and post-nominal credentials
			['Martin Luther King, Jr.', 'Martin'],
			['Jane Smith, MD', 'Jane'],
			['Mary Williams, MD, PhD', 'Mary'],
			['Anna Schmidt, MSc', 'Anna'],
			['Tom Baker, CEO', 'Tom'],
			['King, Martin Luther, Jr.', 'Martin'],
			// Salutations, also stacked
			['Dr. Jane Smith', 'Jane'],
			['Mr. John Doe', 'John'],
			['Prof. Dr. Hans Müller', 'Hans'],
			['Herr Hans Müller', 'Hans'],
			['Frau Anna Schmidt', 'Anna'],
			['Mme Marie Curie', 'Marie'],
			['Mlle Jeanne Dupont', 'Jeanne'],
			['Me Jean Dupont', 'Jean'],
			['Pr Pierre Bernard', 'Pierre'],
			// Leading initials ("goes by middle name")
			['R. Jason Smith', 'Jason'],
			['R. J. Smith', 'R.'],
			// Cyrillic and Greek initials
			['А. С. Пушкин', 'А.'],
			['Пушкин, Александр Сергеевич', 'Александр'],
			['Γ. Κώστας Παπαδόπουλος', 'Κώστας'],
			// CJK names (family name first, single characters are not initials)
			['李明', '李明'],
			['山田 太郎', '山田'],
			['山田　太郎', '山田'],
			['王 小明', '王'],
			['李 明', '李'],
			// Bracketed annotations
			['John Doe (Contracting)', 'John'],
			['(Contracting)', '(Contracting)'],
			['[Bot] Weather', 'Weather'],
			['{External} John Doe', 'John'],
			// Degenerate input
			['Conrad,', 'Conrad'],
			[', Hermes', 'Hermes'],
			['', ''],
			['   ', ''],
		]

		it.each(TEST_CASES)(
			'should return "%s" => "%s"',
			(input, output) => {
				expect(getFirstName(input)).toBe(output)
			},
		)
	})
})
