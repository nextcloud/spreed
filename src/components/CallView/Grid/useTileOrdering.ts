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
	const store = useStore()
	const actorStore = useActorStore()

	// Speakers recently promoted to the first page and the history of
	// promotions, used to keep the relative order of the tiles stable.
	const tempPromotedModels = ref<OrderingModel[]>([])
	const promotedHistoryMask = ref<string[]>([])
	const unpromoteSpeakerTimer: Record<string, ReturnType<typeof setTimeout>> = {}

	const participantsInitialised = computed(() => store.getters.participantsInitialised(token.value))

	const isGuestNonModerator = computed(() => {
		return actorStore.isActorGuest
			&& store.getters.conversation(token.value).participantType !== PARTICIPANT.TYPE.GUEST_MODERATOR
	})

	/**
	 * @param callParticipantModel the participant model
	 */
	function isModelWithVideo(callParticipantModel: OrderingModel) {
		return callParticipantModel.attributes.videoAvailable
			&& (typeof callParticipantModel.attributes.stream === 'object')
	}

	/**
	 * @param callParticipantModel the participant model
	 */
	function isModelWithAudio(callParticipantModel: OrderingModel) {
		const participant = store.getters.getParticipantBySessionId(token.value, callParticipantModel.attributes.nextcloudSessionId)
		if (!participant) {
			return false
		}
		return participant?.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
	}

	/**
	 * @param tilesMap map of session ids to models
	 * @param orderMask ordered session ids to sort the tiles by
	 */
	function getOrderedTiles(tilesMap: Map<string, OrderingModel>, orderMask: string[]) {
		const orderedTiles: OrderingModel[] = []
		const rest: OrderingModel[] = []
		// Get the ordered tiles
		orderMask.forEach((id) => {
			if (tilesMap.has(id)) {
				orderedTiles.push(tilesMap.get(id)!)
			}
		})

		// Add remaining tiles not in orderMask to rest
		tilesMap.forEach((tile, id) => {
			if (!orderMask.includes(id)) {
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
		const tempPromotedModelsSet = new Set(tempPromotedModels.value.map((model) => model.attributes.nextcloudSessionId))
		const videoTilesMap = new Map<string, OrderingModel>()
		const audioTilesMap = new Map<string, OrderingModel>()

		callParticipantModels.value.forEach((model) => {
			if (screensSet.has(model.attributes.peerId)) {
				objectMap.modelsWithScreenshare.push(model)
			} else if (tempPromotedModelsSet.has(model.attributes.nextcloudSessionId)) {
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
	 * @param model the speaker model to unpromote
	 */
	function unpromoteSpeaker(model: OrderingModel) {
		// remove model from the temp promoted speakers
		const index = tempPromotedModels.value.indexOf(model)
		if (index === -1) {
			return
		}

		tempPromotedModels.value.splice(index, 1)
	}

	/**
	 * @param model the speaker model to promote
	 */
	function promoteSpeaker(model: OrderingModel) {
		const id = model.attributes.nextcloudSessionId

		// if model is already in the first page, do nothing
		if (orderedParticipantModels.value.slice(0, slots.value).find((video) => video.attributes.nextcloudSessionId === id)) {
			return
		}

		if (screens.value.includes(model.attributes.peerId)) {
			// tiles with screenshare have a better priority position already
			// do nothing
			return
		}

		// add the model
		if (!tempPromotedModels.value.includes(model)) {
			// remove model from the order history if it exists
			const modelIndex = promotedHistoryMask.value.indexOf(id)
			if (modelIndex !== -1) {
				promotedHistoryMask.value.splice(modelIndex, 1)
			}

			tempPromotedModels.value.unshift(model)
			// add model to the beginning of the ordered models in its category
			promotedHistoryMask.value.unshift(id)
		}
	}

	const speakers = computed(() => callParticipantModels.value.filter((model) => model.attributes.speaking))

	const speakersWithAudioOff = computed(() => tempPromotedModels.value.filter((model) => !model.attributes.audioAvailable))

	watch(speakers, (models) => {
		models.forEach((model) => {
			promoteSpeaker(model)
			clearTimeout(unpromoteSpeakerTimer[model.attributes.nextcloudSessionId])
		})
	})

	watch(speakersWithAudioOff, (newModels, oldModels) => {
		newModels.forEach((speaker) => {
			if (oldModels.includes(speaker)) {
				return
			}
			unpromoteSpeakerTimer[speaker.attributes.nextcloudSessionId] = setTimeout(() => {
				unpromoteSpeaker(speaker)
			}, UNPROMOTE_DELAY)
		})
	})

	// Drop promotion state for participants who have left the call, so the
	// temp-promoted list, the history mask and the pending timers do not
	// accumulate stale entries over the lifetime of a long call.
	watch(() => callParticipantModels.value.map((model) => model.attributes.nextcloudSessionId), (sessionIds) => {
		const presentSessions = new Set(sessionIds)

		tempPromotedModels.value = tempPromotedModels.value
			.filter((model) => presentSessions.has(model.attributes.nextcloudSessionId))
		promotedHistoryMask.value = promotedHistoryMask.value
			.filter((id) => presentSessions.has(id))

		for (const id of Object.keys(unpromoteSpeakerTimer)) {
			if (!presentSessions.has(id)) {
				clearTimeout(unpromoteSpeakerTimer[id])
				delete unpromoteSpeakerTimer[id]
			}
		}
	})

	// Cancel any pending unpromote timers when the owning scope is torn down.
	onScopeDispose(() => {
		Object.values(unpromoteSpeakerTimer).forEach((timer) => clearTimeout(timer))
	})

	return {
		orderedParticipantModels,
	}
}
