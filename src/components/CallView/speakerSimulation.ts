/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Development helper: fakes a conversation between six participants so the
 * multi-speaker layout can be exercised without a room full of people.
 *
 * Only active in debug mode: the call view sets it up and tears it down only
 * when `OC.debug` is truthy.
 *
 * Automatically starts when joining the call of `SIMULATION_TOKEN`, and can be
 * driven by hand from the console:
 *
 *     OCA.Talk.speakerSimulation.start()      // (re)start the timeline
 *     OCA.Talk.speakerSimulation.start(2)     // 2x faster, see `start()`
 *     OCA.Talk.speakerSimulation.stop()
 *     OCA.Talk.speakerSimulation.speak(2, 5)  // make participant 2 speak 5s
 */

import { reactive } from 'vue'
import { callParticipantCollection } from '../../utils/webrtc/index.js'
import { ConnectionState } from '../../utils/webrtc/models/CallParticipantModel.js'
import { placeholderName } from './Grid/gridPlaceholders.ts'

/** Conversation the simulation starts in on its own. */
export const SIMULATION_TOKEN = 'mmhc6xg4'

const PARTICIPANT_COUNT = 6

/**
 * One turn of speech: who speaks, when they start (in seconds from the
 * beginning of the timeline) and for how long.
 *
 * The timeline is built to exercise every rule of the layout, and loops.
 */
const TIMELINE: { speaker: number, at: number, duration: number }[] = [
	// A holds the floor: promoted at 3s, alone in the main area
	{ speaker: 0, at: 0, duration: 20 },
	// B asks a question: the main area splits in two at 9s
	{ speaker: 1, at: 6, duration: 6 },
	// Too short to count, the layout must not move
	{ speaker: 2, at: 14, duration: 2 },
	// C answers properly: three tiles at 21s, the last one centered
	{ speaker: 2, at: 18, duration: 8 },
	// D joins in at 27s with every spot taken, so B (silent since 12s) makes
	// room for it
	{ speaker: 3, at: 24, duration: 10 },
	// E likewise takes over from A at 41s, F from C at 51s
	{ speaker: 4, at: 38, duration: 8 },
	{ speaker: 5, at: 48, duration: 6 },
	// Nobody says anything for a while, so the spots are given up one by one
	// after 30s of silence: D at 64s, E at 76s, F at 84s, and the main area
	// falls back to a single participant
	{ speaker: 1, at: 100, duration: 6 },
	{ speaker: 4, at: 108, duration: 10 },
]

/**
 * Length of a full run, in seconds.
 *
 * The last turn ends at 118s and takes 30s of silence to be demoted, so the
 * run has to be longer than 148s for the next one to start from an empty main
 * area. Restarting any earlier leaves speakers of the previous run promoted,
 * and they are then demoted in the middle of the next one.
 */
const TIMELINE_DURATION = 155

type SimulatedModel = ReturnType<typeof createModel>

/**
 * Build an object with the surface of a `CallParticipantModel` that the call
 * view actually reads. No peer connection is involved, so the tiles show the
 * participant name over an avatar rather than a video.
 *
 * @param index - position of the participant in the simulation
 */
function createModel(index: number) {
	return {
		attributes: reactive({
			peerId: `simulated-peer-${index}`,
			nextcloudSessionId: `simulated-session-${index}`,
			peer: null,
			screenPeer: null,
			actorType: 'users',
			actorId: `${index}-simulated-user-${index}`,
			userId: `${index}-simulated-user-${index}`,
			name: placeholderName(index),
			internal: false,
			connectionState: ConnectionState.CONNECTED,
			negotiating: false,
			connecting: false,
			initialConnection: false,
			connectedAtLeastOnce: true,
			stream: null,
			audioAvailable: true,
			speaking: false,
			videoBlocked: false,
			videoAvailable: false,
			screen: null,
			raisedHand: { state: false, timestamp: null },
		}),
		// Methods the call view calls on a participant model
		on: () => {},
		off: () => {},
		forceMute: () => {},
		setVideoBlocked: () => {},
		setSimulcastVideoQuality: () => {},
		getWebRtc: () => ({ connection: { getSendVideoIfAvailable: () => {} } }),
		destroy: () => {},
	}
}

let models: SimulatedModel[] = []
let timers: ReturnType<typeof setTimeout>[] = []

/**
 * Remove the simulated participants and cancel the timeline.
 */
function stop() {
	timers.forEach((timer) => clearTimeout(timer))
	timers = []

	models.forEach((model) => {
		const index = callParticipantCollection.callParticipantModels.indexOf(model as never)
		if (index !== -1) {
			callParticipantCollection.callParticipantModels.splice(index, 1)
		}
	})
	models = []

	console.info('[speaker simulation] stopped')
}

/**
 * Make one of the simulated participants speak.
 *
 * @param index - position of the participant in the simulation
 * @param duration - how long they speak, in seconds
 */
function speak(index: number, duration: number) {
	const model = models[index]
	if (!model) {
		console.warn('[speaker simulation] no participant', index)
		return
	}

	model.attributes.speaking = true
	const timer = setTimeout(() => {
		timers.splice(timers.indexOf(timer), 1)
		model.attributes.speaking = false
	}, duration * 1000)
	timers.push(timer)
}

/**
 * Add the simulated participants and play the timeline in a loop.
 *
 * @param speed - how much faster than real time to play, 1 by default. The
 *   promotion and demotion delays are NOT scaled, so the turns get shorter
 *   while the 3s needed to be promoted does not: past 2x almost nothing is
 *   promoted any more, which is only useful to check that brief exchanges
 *   leave the layout alone. Use `speak()` by hand to make people talk over
 *   each other.
 */
function start(speed = 1) {
	stop()

	models = Array.from({ length: PARTICIPANT_COUNT }, (_, index) => createModel(index))
	callParticipantCollection.callParticipantModels.push(...(models as never[]))

	const scheduleRun = () => {
		TIMELINE.forEach(({ speaker, at, duration }) => {
			timers.push(setTimeout(() => speak(speaker, duration / speed), (at / speed) * 1000))
		})
		timers.push(setTimeout(scheduleRun, (TIMELINE_DURATION / speed) * 1000))
	}
	scheduleRun()

	console.info(`[speaker simulation] started with ${PARTICIPANT_COUNT} participants at ${speed}x`)
}

/**
 * Expose the controls and start the simulation if the call is the one it was
 * written for.
 *
 * Only called by the call view when `OC.debug` is truthy.
 *
 * @param token - token of the conversation being joined
 */
export function setUpSpeakerSimulation(token: string) {
	if (window.OCA?.Talk) {
		// @ts-expect-error: OCA is not typed
		window.OCA.Talk.speakerSimulation = { start, stop, speak }
	}

	if (token === SIMULATION_TOKEN) {
		start()
	}
}

/**
 * Counterpart of {@link setUpSpeakerSimulation}, to be called when the call
 * view goes away.
 */
export function tearDownSpeakerSimulation() {
	stop()
	// @ts-expect-error: OCA is not typed
	delete window.OCA?.Talk?.speakerSimulation
}
