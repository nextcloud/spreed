/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'

import { computed, onScopeDispose, ref, watch } from 'vue'
import { useStore } from 'vuex'
import { PARTICIPANT } from '../../../constants.ts'
import { useActorStore } from '../../../stores/actor.ts'

/**
 * Time (in ms) a speaker whose audio went off stays promoted before being
 * demoted again.
 */
const UNPROMOTE_DELAY = 10000

/**
 * Minimal shape of a call participant model the ordering logic relies on.
 * The real models expose many more attributes, but only these are read here.
 */
export type OrderingModel = {
	attributes: {
		peerId: string
		nextcloudSessionId: string
		stream?: object | null
		videoAvailable?: boolean
		audioAvailable?: boolean
		speaking?: boolean
	}
}

type UseTileOrderingOptions = {
	/** All call participant models, in their source order */
	callParticipantModels: Readonly<Ref<OrderingModel[]>>
	/** Peer ids currently sharing their screen */
	screens: Readonly<Ref<string[]>>
	/** Token of the conversation the grid belongs to */
	token: Readonly<Ref<string>>
	/** Number of tiles shown on the first page (used to skip already-visible speakers) */
	slots: Readonly<Ref<number>>
}

/**
 * Orders the call participant tiles and keeps recently active speakers promoted
 * to the first page.
 *
 * Tiles are grouped by category (screenshare > temporarily promoted speakers >
 * video enabled > audio only > no permissions) and, within the video and audio
 * groups, sorted by a promotion history mask so that a speaker keeps its
 * relative position once promoted. Guests (whose participant store is not
 * initialised) bypass the ordering and get the models in their source order.
 *
 * @param options - the reactive inputs driving the ordering
 * @param options.callParticipantModels - all call participant models
 * @param options.screens - peer ids currently sharing their screen
 * @param options.token - token of the conversation the grid belongs to
 * @param options.slots - number of tiles shown on the first page
 */
export function useTileOrdering({
	callParticipantModels,
	screens,
	token,
	slots,
}: UseTileOrderingOptions) {
	const vuexStore = useStore()
	const actorStore = useActorStore()

	// Session ids of the speakers recently promoted to the first page and the
	// history of promotions, used to keep the relative order of the tiles
	// stable. Only ids are kept, so no participant model is referenced here.
	const tempPromotedSessionIds = ref(new Set<string>())
	const promotedHistoryMask = ref<string[]>([])
	const unpromoteSpeakerTimer: Record<string, ReturnType<typeof setTimeout>> = {}

	const participantsInitialised = computed(() => vuexStore.getters.participantsInitialised(token.value))

	const isGuestNonModerator = computed(() => {
		return actorStore.isActorGuest
			&& vuexStore.getters.conversation(token.value)?.participantType !== PARTICIPANT.TYPE.GUEST_MODERATOR
	})

	/**
	 * @param callParticipantModel the participant model
	 */
	function isModelWithVideo(callParticipantModel: OrderingModel): boolean {
		return !!callParticipantModel.attributes.videoAvailable
			&& callParticipantModel.attributes.stream !== null
			&& (typeof callParticipantModel.attributes.stream === 'object')
	}

	/**
	 * @param callParticipantModel the participant model
	 */
	function isModelWithAudio(callParticipantModel: OrderingModel): boolean {
		const participant = vuexStore.getters.getParticipantBySessionId(token.value, callParticipantModel.attributes.nextcloudSessionId)
		if (!participant) {
			return false
		}
		return !!(participant?.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO)
	}

	/**
	 * @param tilesMap map of session ids to models
	 * @param orderMask ordered session ids to sort the tiles by
	 */
	function getOrderedTiles(tilesMap: Map<string, OrderingModel>, orderMask: string[]) {
		const orderedTiles: OrderingModel[] = []
		const orderedIds = new Set<string>()
		// Emit the tiles referenced by the mask, in mask order
		orderMask.forEach((id) => {
			const tile = tilesMap.get(id)
			if (tile) {
				orderedTiles.push(tile)
				orderedIds.add(id)
			}
		})

		// Append the remaining tiles in their original (insertion) order
		const rest: OrderingModel[] = []
		tilesMap.forEach((tile, id) => {
			if (!orderedIds.has(id)) {
				rest.push(tile)
			}
		})

		return [...orderedTiles, ...rest]
	}

	const orderedParticipantModels = computed<OrderingModel[]>(() => {
		// Dynamic ordering is not possible for guests because
		// participants store is not initialized
		if (isGuestNonModerator.value) {
			return callParticipantModels.value
		}

		const objectMap = {
			modelsWithScreenshare: [] as OrderingModel[],
			modelsTempPromoted: [] as OrderingModel[],
			modelsWithVideoEnabled: [] as OrderingModel[],
			modelsWithAudioOnly: [] as OrderingModel[],
			modelsWithNoPermissions: [] as OrderingModel[],
		}
		const screensSet = new Set(screens.value)
		const videoTilesMap = new Map<string, OrderingModel>()
		const audioTilesMap = new Map<string, OrderingModel>()

		callParticipantModels.value.forEach((model) => {
			if (screensSet.has(model.attributes.peerId)) {
				objectMap.modelsWithScreenshare.push(model)
			} else if (tempPromotedSessionIds.value.has(model.attributes.nextcloudSessionId)) {
				objectMap.modelsTempPromoted.push(model)
			} else if (isModelWithVideo(model)) {
				videoTilesMap.set(model.attributes.nextcloudSessionId, model)
			} else if (participantsInitialised.value && isModelWithAudio(model)) {
				audioTilesMap.set(model.attributes.nextcloudSessionId, model)
			} else {
				objectMap.modelsWithNoPermissions.push(model)
			}
		})

		objectMap.modelsWithVideoEnabled = getOrderedTiles(videoTilesMap, promotedHistoryMask.value)
		objectMap.modelsWithAudioOnly = getOrderedTiles(audioTilesMap, promotedHistoryMask.value)

		return [
			...objectMap.modelsWithScreenshare,
			...objectMap.modelsTempPromoted,
			...objectMap.modelsWithVideoEnabled,
			...objectMap.modelsWithAudioOnly,
			...objectMap.modelsWithNoPermissions,
		]
	})

	/**
	 * @param sessionId session id of the speaker to unpromote
	 */
	function unpromoteSpeaker(sessionId: string) {
		tempPromotedSessionIds.value.delete(sessionId)
	}

	/**
	 * @param model the speaker model to promote
	 */
	function promoteSpeaker(model: OrderingModel) {
		const id = model.attributes.nextcloudSessionId

		if (slots.value <= 0) {
			// Nothing to promote
			return
		}

		// if model is already in the first page, do nothing
		if (orderedParticipantModels.value.slice(0, slots.value).find((video) => video.attributes.nextcloudSessionId === id)) {
			return
		}

		if (screens.value.includes(model.attributes.peerId)) {
			// tiles with screenshare have a better priority position already
			// do nothing
			return
		}

		// add the speaker
		if (!tempPromotedSessionIds.value.has(id)) {
			// remove the speaker from the order history if it exists
			const maskIndex = promotedHistoryMask.value.indexOf(id)
			if (maskIndex !== -1) {
				promotedHistoryMask.value.splice(maskIndex, 1)
			}

			tempPromotedSessionIds.value.add(id)
			// add speaker to the beginning of the ordered models in its category
			promotedHistoryMask.value.unshift(id)
		}
	}

	const speakers = computed(() => callParticipantModels.value.filter((model) => model.attributes.speaking))

	const promotedSessionIdsWithAudioOff = computed(() => {
		const sessionIdsWithAudio = new Set(callParticipantModels.value
			.filter(({ attributes }) => attributes.audioAvailable)
			.map(({ attributes }) => attributes.nextcloudSessionId))
		// All that aren't in Set - muted or disconnected
		return [...tempPromotedSessionIds.value].filter((id) => !sessionIdsWithAudio.has(id))
	})

	watch(speakers, (models) => {
		models.forEach((model) => {
			promoteSpeaker(model)
			clearTimeout(unpromoteSpeakerTimer[model.attributes.nextcloudSessionId])
			delete unpromoteSpeakerTimer[model.attributes.nextcloudSessionId]
		})
	})

	watch(promotedSessionIdsWithAudioOff, (sessionIds) => {
		sessionIds.forEach((sessionId) => {
			if (unpromoteSpeakerTimer[sessionId]) {
				// an unpromote is already pending for this speaker
				return
			}
			unpromoteSpeakerTimer[sessionId] = setTimeout(() => {
				delete unpromoteSpeakerTimer[sessionId]
				unpromoteSpeaker(sessionId)
			}, UNPROMOTE_DELAY)
		})
	})

	// Cancel any pending unpromote timers when the owning scope is torn down.
	onScopeDispose(() => {
		Object.values(unpromoteSpeakerTimer).forEach((timer) => clearTimeout(timer))
	})

	return {
		orderedParticipantModels,
	}
}
