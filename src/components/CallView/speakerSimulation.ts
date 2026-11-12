/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Development helper: fakes a conversation between six participants, with
 * test pattern videos and live transcripts, so the multi-speaker layout and
 * the transcript panel can be exercised without a room full of people.
 *
 * Only active in debug mode: the call view sets it up and tears it down only
 * when `OC.debug` is truthy.
 *
 * Automatically starts when joining the call of `SIMULATION_TOKEN`, and can be
 * driven by hand from the console:
 *
 *     OCA.Talk.speakerSimulation.start()      // (re)start the timeline
 *     OCA.Talk.speakerSimulation.start(2)     // 2x faster, see `start()`
 *     OCA.Talk.speakerSimulation.start(1, 30, 640) // 30fps, 640px wide videos
 *     OCA.Talk.speakerSimulation.stop()
 *     OCA.Talk.speakerSimulation.speak(2, 5)  // participant 2 speaks and is transcribed for 5s
 *     OCA.Talk.speakerSimulation.transcript(2, 'Hello', 'en', false) // one chunk, shows the panel
 */

import { markRaw, nextTick, reactive } from 'vue'
import { useCallViewStore } from '../../stores/callView.ts'
import { callParticipantCollection } from '../../utils/webrtc/index.js'
import { ConnectionState } from '../../utils/webrtc/models/CallParticipantModel.js'
import { placeholderName } from './Grid/gridPlaceholders.ts'

/** Conversation the simulation starts in on its own. */
export const SIMULATION_TOKEN = 'mmhc6xg4'

const PARTICIPANT_COUNT = 6

/** Words transcribed while a simulated participant speaks, in a loop. */
const WORDS = 'lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua'.split(' ')

/** Delay between two transcript updates, in seconds. */
const UPDATE_INTERVAL = 2

/** Words added by each transcript update. */
const WORDS_PER_UPDATE = 4

/** Words sent as partial updates of one chunk before it is final. */
const CHUNK_LENGTH = 8

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

type TranscriptHandler = (model: object, message: string, languageId: string, final: boolean) => void

/**
 * Build an object with the surface of a `CallParticipantModel` that the call
 * view actually reads. No peer connection is involved, so the tiles show a
 * canvas test pattern rather than a camera video.
 *
 * @param index - position of the participant in the simulation
 */
function createModel(index: number) {
	const transcriptHandlers = new Set<TranscriptHandler>()

	const attributes = reactive({
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
		stream: null as MediaStream | null,
		audioAvailable: true,
		speaking: false,
		videoBlocked: false,
		videoAvailable: false,
		screen: null,
		raisedHand: { state: false, timestamp: null },
	})

	return {
		attributes,
		get: (key: keyof typeof attributes) => attributes[key],
		transcriptHandlers,
		// Methods the call view calls on a participant model
		on: (event: string, handler: TranscriptHandler) => {
			if (event === 'transcript') {
				transcriptHandlers.add(handler)
			}
		},
		off: (event: string, handler: TranscriptHandler) => {
			if (event === 'transcript') {
				transcriptHandlers.delete(handler)
			}
		},
		forceMute: () => {},
		setVideoBlocked: () => {},
		setSimulcastVideoQuality: () => {},
		getWebRtc: () => ({ connection: { getSendVideoIfAvailable: () => {} } }),
		destroy: () => {},
	}
}

let models: SimulatedModel[] = []
let timers: ReturnType<typeof setTimeout>[] = []
let videoIntervals: ReturnType<typeof setInterval>[] = []
let originalDisableLiveTranscription: ReturnType<typeof useCallViewStore>['disableLiveTranscription'] | undefined

/**
 * Show the live transcript panel with the simulated participants subscribed.
 */
async function showTranscript() {
	const callViewStore = useCallViewStore()
	// Skip the server request on leaving the call, there is no ExApp to disable
	originalDisableLiveTranscription = callViewStore.disableLiveTranscription
	callViewStore.disableLiveTranscription = async () => {
		callViewStore.isLiveTranscriptionEnabled = false
	}

	// The renderer subscribes only to the models present when it is mounted
	callViewStore.isLiveTranscriptionEnabled = false
	await nextTick()
	callViewStore.isLiveTranscriptionEnabled = true
}

/**
 * Hide the live transcript panel and restore the store action replaced in
 * {@link showTranscript}.
 */
function hideTranscript() {
	if (!originalDisableLiveTranscription) {
		return
	}

	const callViewStore = useCallViewStore()
	callViewStore.disableLiveTranscription = originalDisableLiveTranscription
	originalDisableLiveTranscription = undefined
	callViewStore.isLiveTranscriptionEnabled = false
}

/**
 * Remove the simulated participants and cancel the timeline.
 */
function stop() {
	timers.forEach((timer) => clearTimeout(timer))
	timers = []

	hideTranscript()

	videoIntervals.forEach((interval) => clearInterval(interval))
	videoIntervals = []

	models.forEach((model) => {
		model.attributes.stream?.getTracks().forEach((track) => track.stop())
		const index = callParticipantCollection.callParticipantModels.indexOf(model as never)
		if (index !== -1) {
			callParticipantCollection.callParticipantModels.splice(index, 1)
		}
	})
	models = []

	console.info('[speaker simulation] stopped')
}

/**
 * Play a moving test pattern as the video of a simulated participant.
 *
 * @param model - the simulated participant
 * @param index - position of the participant in the simulation
 * @param fps - frames per second
 * @param width - width of the video, in pixels
 */
function startVideo(model: SimulatedModel, index: number, fps: number, width: number) {
	// Alternate aspect ratios to check how the tiles fit both
	const [height, ratio] = index % 2 ? [width * 3 / 4, '4:3'] : [width * 9 / 16, '16:9']
	// Drawing sizes below are for a 640px wide video
	const scale = width / 640

	const canvas = document.createElement('canvas')
	canvas.width = width
	canvas.height = height
	const context = canvas.getContext('2d')!
	videoIntervals.push(setInterval(() => {
		context.fillStyle = `hsl(${index * 60} 50% 40%)`
		context.fillRect(0, 0, width, height)
		// Frame on the edges shows whether the tile crops the video
		context.strokeStyle = 'white'
		context.lineWidth = 8 * scale
		context.strokeRect(4 * scale, 4 * scale, width - 8 * scale, height - 8 * scale)
		context.fillStyle = 'white'
		context.font = `${32 * scale}px sans-serif`
		context.textAlign = 'center'
		context.textBaseline = 'middle'
		context.fillText(`${ratio} ${model.attributes.name}`, width / 2, height / 2 - 20 * scale)
		context.fillText(new Date().toLocaleTimeString(), width / 2, height / 2 + 20 * scale)
		// Moving square, so a frozen video is easy to spot
		context.fillRect((Date.now() / 5 * scale) % width, height - 80 * scale, 40 * scale, 40 * scale)
	}, 1000 / fps))

	// Not reactive, the video element needs the real MediaStream
	model.attributes.stream = markRaw(canvas.captureStream(fps))
	model.attributes.videoAvailable = true
}

/**
 * Run a callback once, keeping the timer cancellable by {@link stop}.
 *
 * @param callback - function to run
 * @param delay - delay in seconds
 */
function schedule(callback: () => void, delay: number) {
	const timer = setTimeout(() => {
		timers.splice(timers.indexOf(timer), 1)
		callback()
	}, delay * 1000)
	timers.push(timer)
}

/**
 * Make one of the simulated participants speak, sending a transcript update
 * every {@link UPDATE_INTERVAL} seconds.
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
	schedule(() => {
		model.attributes.speaking = false
	}, duration)

	const updateCount = Math.max(1, Math.floor(duration / UPDATE_INTERVAL))
	for (let update = 1; update <= updateCount; update++) {
		const wordCount = update * WORDS_PER_UPDATE
		const chunkStart = Math.floor((wordCount - 1) / CHUNK_LENGTH) * CHUNK_LENGTH
		const message = Array.from({ length: wordCount - chunkStart }, (_, k) => WORDS[(chunkStart + k) % WORDS.length]).join(' ')
		const final = wordCount % CHUNK_LENGTH === 0 || update === updateCount
		schedule(() => transcript(index, message, 'en', final), update * UPDATE_INTERVAL)
	}
}

/**
 * Send a live transcript chunk from one of the simulated participants.
 *
 * The transcript panel is shown on the first chunk. Also called by
 * {@link speak}, so the timeline is transcribed as well.
 *
 * @param index - position of the participant in the simulation
 * @param message - transcribed text
 * @param languageId - language of the text
 * @param final - false to replace the chunk on the next call
 */
async function transcript(index: number, message: string, languageId = 'en', final = true) {
	const model = models[index]
	if (!model) {
		console.warn('[speaker simulation] no participant', index)
		return
	}

	if (!originalDisableLiveTranscription) {
		await showTranscript()
	}

	model.transcriptHandlers.forEach((handler) => handler(model, message, languageId, final))
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
 * @param fps - frames per second of the simulated videos
 * @param width - width of the simulated videos, in pixels
 */
function start(speed = 1, fps = 15, width = 320) {
	stop()

	models = Array.from({ length: PARTICIPANT_COUNT }, (_, index) => createModel(index))
	models.forEach((model, index) => startVideo(model, index, fps, width))
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
		window.OCA.Talk.speakerSimulation = { start, stop, speak, transcript }
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
