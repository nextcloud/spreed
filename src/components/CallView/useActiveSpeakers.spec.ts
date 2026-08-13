/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { SpeakerModel } from './useActiveSpeakers.ts'

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { effectScope, nextTick, reactive, ref } from 'vue'
import { DEMOTE_DELAY, MAX_PROMOTED_SPEAKERS, PROMOTE_DELAY, useActiveSpeakers } from './useActiveSpeakers.ts'

let sessionCounter = 0

/**
 * Build a call participant model with the reactive attributes the speaker
 * tracking reads.
 *
 * @param speaking - whether the participant starts out speaking
 */
function makeModel(speaking = false): SpeakerModel {
	return {
		attributes: reactive({
			nextcloudSessionId: `session-${sessionCounter++}`,
			speaking,
		}),
	}
}

/**
 * Instantiate the composable inside an effect scope, so its watchers and its
 * `onScopeDispose` cleanup behave as they would in a component.
 *
 * @param models - the call participant models
 */
function createSpeakers(models: SpeakerModel[] = []) {
	const callParticipantModels = ref(models)
	const enabled = ref(true)

	const scope = effectScope()
	const { promotedSessionIds } = scope.run(() => useActiveSpeakers({
		callParticipantModels,
		enabled,
	}))!

	return { callParticipantModels, enabled, promotedSessionIds, scope }
}

/**
 * Let the watchers run, fire the timers due within the given time, then let the
 * watchers run again.
 *
 * @param ms - time to advance the fake clock by
 */
async function advance(ms: number) {
	await nextTick()
	vi.advanceTimersByTime(ms)
	await nextTick()
}

describe('useActiveSpeakers', () => {
	beforeEach(() => {
		sessionCounter = 0
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	describe('promotion', () => {
		test('promotes a participant that spoke long enough', async () => {
			const model = makeModel()
			const { promotedSessionIds } = createSpeakers([model])

			model.attributes.speaking = true

			await advance(PROMOTE_DELAY - 1)
			expect(promotedSessionIds.value).toEqual([])

			await advance(1)
			expect(promotedSessionIds.value).toEqual(['session-0'])
		})

		test('ignores a short interjection', async () => {
			const model = makeModel()
			const { promotedSessionIds } = createSpeakers([model])

			model.attributes.speaking = true
			await advance(PROMOTE_DELAY - 500)
			model.attributes.speaking = false

			await advance(PROMOTE_DELAY)
			expect(promotedSessionIds.value).toEqual([])
		})

		test('requires uninterrupted speech to promote', async () => {
			const model = makeModel()
			const { promotedSessionIds } = createSpeakers([model])

			// Two bursts adding up to more than the delay, but neither long enough
			for (let burst = 0; burst < 2; burst++) {
				model.attributes.speaking = true
				await advance(PROMOTE_DELAY - 500)
				model.attributes.speaking = false
				await advance(100)
			}
			expect(promotedSessionIds.value).toEqual([])

			model.attributes.speaking = true
			await advance(PROMOTE_DELAY)
			expect(promotedSessionIds.value).toEqual(['session-0'])
		})

		test('keeps the speakers in the order they became active', async () => {
			const models = [makeModel(), makeModel()]
			const { promotedSessionIds } = createSpeakers(models)

			models[1].attributes.speaking = true
			await advance(PROMOTE_DELAY)
			models[0].attributes.speaking = true
			await advance(PROMOTE_DELAY)

			expect(promotedSessionIds.value).toEqual(['session-1', 'session-0'])
		})
	})

	describe('demotion', () => {
		test('demotes a participant that stayed silent', async () => {
			const model = makeModel()
			const { promotedSessionIds } = createSpeakers([model])

			model.attributes.speaking = true
			await advance(PROMOTE_DELAY)
			model.attributes.speaking = false

			await advance(DEMOTE_DELAY - 1)
			expect(promotedSessionIds.value).toEqual(['session-0'])

			await advance(1)
			expect(promotedSessionIds.value).toEqual([])
		})

		test('cancels the demotion when the participant speaks again', async () => {
			const model = makeModel()
			const { promotedSessionIds } = createSpeakers([model])

			model.attributes.speaking = true
			await advance(PROMOTE_DELAY)
			model.attributes.speaking = false
			await advance(DEMOTE_DELAY - 1000)
			model.attributes.speaking = true

			await advance(DEMOTE_DELAY)
			expect(promotedSessionIds.value).toEqual(['session-0'])
		})

		test('demotes a participant that left the call right away', async () => {
			const models = [makeModel(), makeModel()]
			const { callParticipantModels, promotedSessionIds } = createSpeakers(models)

			models.forEach((model) => {
				model.attributes.speaking = true
			})
			await advance(PROMOTE_DELAY)
			expect(promotedSessionIds.value).toEqual(['session-0', 'session-1'])

			// Left while still flagged as speaking
			callParticipantModels.value = [models[1]]
			await nextTick()
			expect(promotedSessionIds.value).toEqual(['session-1'])
		})
	})

	describe('slots', () => {
		/**
		 * Promote the given models one after the other, each of them speaking
		 * long enough and then falling silent.
		 *
		 * @param models - the models to promote, in order
		 */
		async function promoteInTurn(models: SpeakerModel[]) {
			for (const model of models) {
				model.attributes.speaking = true
				await advance(PROMOTE_DELAY)
				model.attributes.speaking = false
				await advance(100)
			}
		}

		test('promotes no more than three speakers at once', async () => {
			const models = Array.from({ length: 4 }, () => makeModel())
			const { promotedSessionIds } = createSpeakers(models)

			await promoteInTurn(models)

			expect(promotedSessionIds.value).toHaveLength(MAX_PROMOTED_SPEAKERS)
		})

		test('gives the spot of the least recent speaker to a new one', async () => {
			const models = Array.from({ length: 4 }, () => makeModel())
			const { promotedSessionIds } = createSpeakers(models)

			await promoteInTurn(models.slice(0, 3))
			expect(promotedSessionIds.value).toEqual(['session-0', 'session-1', 'session-2'])

			// The first one spoke longest ago, so it makes room
			await promoteInTurn([models[3]])
			expect(promotedSessionIds.value).toEqual(['session-1', 'session-2', 'session-3'])
		})

		test('keeps a speaker that spoke recently over one that did not', async () => {
			const models = Array.from({ length: 4 }, () => makeModel())
			const { promotedSessionIds } = createSpeakers(models)

			await promoteInTurn(models.slice(0, 3))
			// The first one chimes in again, so the second is now the stalest
			await promoteInTurn([models[0]])
			await promoteInTurn([models[3]])

			// Speaking again keeps a promoted speaker in place rather than
			// moving its tile around
			expect(promotedSessionIds.value).toEqual(['session-0', 'session-2', 'session-3'])
		})

		test('does not evict a speaker that is still talking', async () => {
			const models = Array.from({ length: 4 }, () => makeModel())
			const { promotedSessionIds } = createSpeakers(models)

			// The first one never stops talking, the others take turns
			models[0].attributes.speaking = true
			await advance(PROMOTE_DELAY)
			await promoteInTurn(models.slice(1, 3))
			await promoteInTurn([models[3]])

			expect(promotedSessionIds.value).toContain('session-0')
			expect(promotedSessionIds.value).not.toContain('session-1')
		})
	})

	describe('lifecycle', () => {
		test('forgets the speakers when disabled', async () => {
			const model = makeModel()
			const { enabled, promotedSessionIds } = createSpeakers([model])

			model.attributes.speaking = true
			await advance(PROMOTE_DELAY)
			expect(promotedSessionIds.value).toEqual(['session-0'])

			enabled.value = false
			await nextTick()
			expect(promotedSessionIds.value).toEqual([])
		})

		test('does not track speakers while disabled', async () => {
			const model = makeModel()
			const { enabled, promotedSessionIds } = createSpeakers([model])

			enabled.value = false
			await nextTick()
			model.attributes.speaking = true

			await advance(PROMOTE_DELAY)
			expect(promotedSessionIds.value).toEqual([])
		})

		test('cancels pending timers when the scope is disposed', async () => {
			const model = makeModel()
			const { promotedSessionIds, scope } = createSpeakers([model])

			model.attributes.speaking = true
			await nextTick()
			scope.stop()

			await advance(PROMOTE_DELAY)
			expect(promotedSessionIds.value).toEqual([])
		})
	})
})
