/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { OrderingModel } from './useTileOrdering.ts'

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, test, vi } from 'vitest'
import { effectScope, nextTick, reactive, ref } from 'vue'
import { useStore } from 'vuex'
import { ATTENDEE, PARTICIPANT } from '../../../constants.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useTileOrdering } from './useTileOrdering.ts'

vi.mock('vuex')

type ModelAttributes = Partial<OrderingModel['attributes']> & { audioPublisher?: boolean }

let sessionCounter = 0

/**
 * Build a call participant model with the reactive attributes the ordering
 * logic reads.
 *
 * @param attributes - the attributes to seed the model with. `audioPublisher`
 *   is a test-only flag that makes the mocked participant getter grant the
 *   PUBLISH_AUDIO permission for this model's session.
 */
function makeModel(attributes: ModelAttributes = {}): OrderingModel & { audioPublisher: boolean } {
	const { audioPublisher = false, ...rest } = attributes
	return {
		audioPublisher,
		attributes: reactive({
			peerId: `peer-${sessionCounter}`,
			nextcloudSessionId: `session-${sessionCounter++}`,
			...rest,
		}),
	}
}

/**
 * Instantiate the composable with sensible defaults, overridable per test.
 *
 * @param options - overrides for the reactive inputs
 * @param options.models - the call participant models
 * @param options.screens - peer ids sharing their screen
 * @param options.slots - number of tiles on the first page
 */
function createOrdering({
	models = [] as ReturnType<typeof makeModel>[],
	screens = [] as string[],
	slots = 4,
} = {}) {
	const callParticipantModels = ref(models)
	const screensRef = ref(screens)
	const slotsRef = ref(slots)

	// Run inside an effect scope so the composable's watchers and scope
	// cleanup (onScopeDispose) have a scope to attach to, as they would in a
	// component.
	const scope = effectScope()
	const { orderedParticipantModels } = scope.run(() => useTileOrdering({
		callParticipantModels,
		screens: screensRef,
		token: ref('token'),
		slots: slotsRef,
	}))!

	return { callParticipantModels, screens: screensRef, slots: slotsRef, orderedParticipantModels, scope }
}

describe('useTileOrdering', () => {
	beforeEach(() => {
		sessionCounter = 0
		setActivePinia(createPinia())

		vi.mocked(useStore).mockReturnValue({
			getters: {
				participantsInitialised: () => true,
				conversation: () => ({ participantType: PARTICIPANT.TYPE.USER }),
				getParticipantBySessionId: (_token: string, sessionId: string) => {
					const model = allModels.find((m) => m.attributes.nextcloudSessionId === sessionId)
					if (!model?.audioPublisher) {
						return undefined
					}
					return { permissions: PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO }
				},
			},
		} as unknown as ReturnType<typeof useStore>)
	})

	// Registry the mocked getter looks models up in
	let allModels: ReturnType<typeof makeModel>[] = []

	describe('bypass', () => {
		test('returns the models in source order for guests', () => {
			const actorStore = useActorStore()
			actorStore.actorType = ATTENDEE.ACTOR_TYPE.GUESTS

			// A screenshare that would otherwise be reordered to the front
			const video = makeModel({ videoAvailable: true, stream: {} })
			const screenshare = makeModel()
			const models = [video, screenshare]
			allModels = models
			const { orderedParticipantModels } = createOrdering({ models, screens: [screenshare.attributes.peerId] })

			expect(orderedParticipantModels.value).toEqual(models)
		})
	})

	describe('categorisation', () => {
		test('orders tiles by category: screenshare, video, audio, no permissions', () => {
			const screenshare = makeModel()
			const video = makeModel({ videoAvailable: true, stream: {} })
			const audio = makeModel({ audioPublisher: true })
			const noPermissions = makeModel()
			// Deliberately shuffled input order
			const models = [noPermissions, audio, screenshare, video]
			allModels = models

			const { orderedParticipantModels } = createOrdering({
				models,
				screens: [screenshare.attributes.peerId],
			})

			expect(orderedParticipantModels.value).toEqual([screenshare, video, audio, noPermissions])
		})
	})

	describe('speaker promotion', () => {
		test('promotes a speaker that is not on the first page', async () => {
			const first = makeModel({ audioPublisher: true, audioAvailable: true })
			const second = makeModel({ audioPublisher: true, audioAvailable: true })
			const models = [first, second]
			allModels = models

			const { orderedParticipantModels } = createOrdering({ models, slots: 1 })
			expect(orderedParticipantModels.value).toEqual([first, second])

			// The second tile starts speaking while off the first page
			second.attributes.speaking = true
			await nextTick()

			expect(orderedParticipantModels.value).toEqual([second, first])
		})

		test('does not promote a speaker already on the first page', async () => {
			const first = makeModel({ audioPublisher: true, audioAvailable: true })
			const second = makeModel({ audioPublisher: true, audioAvailable: true })
			const models = [first, second]
			allModels = models

			const { orderedParticipantModels } = createOrdering({ models, slots: 2 })

			first.attributes.speaking = true
			await nextTick()

			expect(orderedParticipantModels.value).toEqual([first, second])
		})
	})

	describe('participant churn', () => {
		test('drops a departed participant and keeps ordering the rest', async () => {
			const a = makeModel({ audioPublisher: true, audioAvailable: true })
			const b = makeModel({ audioPublisher: true, audioAvailable: true })
			const c = makeModel({ audioPublisher: true, audioAvailable: true })
			const models = [a, b, c]
			allModels = models

			const { orderedParticipantModels, callParticipantModels } = createOrdering({ models, slots: 1 })

			// b speaks and gets promoted to the front
			b.attributes.speaking = true
			await nextTick()
			expect(orderedParticipantModels.value).toEqual([b, a, c])

			// b leaves the call
			allModels = [a, c]
			callParticipantModels.value = [a, c]
			await nextTick()
			expect(orderedParticipantModels.value).toEqual([a, c])

			// promotion still works for the remaining participants
			c.attributes.speaking = true
			await nextTick()
			expect(orderedParticipantModels.value).toEqual([c, a])
		})

		test('keeps the promoted place when a participant reconnects with the same session', async () => {
			const a = makeModel({ audioPublisher: true, audioAvailable: true })
			const b = makeModel({ audioPublisher: true, audioAvailable: true })
			const models = [a, b]
			allModels = models

			const { orderedParticipantModels, callParticipantModels } = createOrdering({ models, slots: 1 })

			// b speaks and gets promoted to the front
			b.attributes.speaking = true
			await nextTick()
			expect(orderedParticipantModels.value).toEqual([b, a])

			// b drops out of the call and comes back with the same session id
			allModels = [a]
			callParticipantModels.value = [a]
			await nextTick()
			b.attributes.speaking = false
			allModels = [a, b]
			callParticipantModels.value = [a, b]
			await nextTick()

			expect(orderedParticipantModels.value).toEqual([b, a])
		})

		test('lets a pending unpromote timer of a departed participant run out harmlessly', async () => {
			vi.useFakeTimers()
			try {
				const a = makeModel({ audioPublisher: true, audioAvailable: true })
				const b = makeModel({ audioPublisher: true, audioAvailable: true })
				const models = [a, b]
				allModels = models

				const { orderedParticipantModels, callParticipantModels } = createOrdering({ models, slots: 1 })

				// b speaks (gets promoted) and then its audio goes off, which
				// schedules an unpromote timer for it.
				b.attributes.speaking = true
				await nextTick()
				b.attributes.audioAvailable = false
				await nextTick()

				// b leaves the call before the timer fires
				allModels = [a]
				callParticipantModels.value = [a]
				await nextTick()

				vi.runAllTimers()
				await nextTick()

				expect(orderedParticipantModels.value).toEqual([a])
			} finally {
				vi.useRealTimers()
			}
		})
	})
})
