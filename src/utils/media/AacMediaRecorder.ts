/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { BufferTarget, canEncodeAudio, MediaStreamAudioTrackSource, Mp4OutputFormat, Output, Quality } from 'mediabunny'

const NUMBER_OF_CHANNELS = 1
const QUALITY = new Quality({ bitrate: 48_000 })

/**
 * Subset of MediaRecorder API, recording AAC in a non-fragmented MP4 file
 */
class AacMediaRecorder {
	state: RecordingState = 'inactive'
	ondataavailable: ((event: { data: Blob }) => void) | null = null
	onstop: (() => void) | null = null
	// Not stored as Output on the instance, as component wraps it into a reactive proxy
	private output: Promise<Output<Mp4OutputFormat, BufferTarget>> | null = null

	constructor(private stream: MediaStream) {}

	start() {
		this.state = 'recording'
		this.output = this.startOutput()
	}

	stop() {
		this.state = 'inactive'
		this.output?.then(async (output) => {
			await output.finalize()
			this.ondataavailable?.({ data: new Blob([output.target.buffer as ArrayBuffer], { type: 'audio/mp4' }) })
			this.onstop?.()
		}).catch((error) => {
			console.error('Error while encoding audio', error)
		})
	}

	private async startOutput() {
		const output = new Output({
			format: new Mp4OutputFormat({ fastStart: 'in-memory' }),
			target: new BufferTarget(),
		})
		const source = new MediaStreamAudioTrackSource(this.stream.getAudioTracks()[0], {
			codec: 'aac',
			quality: QUALITY,
			transform: { numberOfChannels: NUMBER_OF_CHANNELS },
		})
		source.errorPromise.catch((error) => {
			console.error('Error while recording audio', error)
		})
		output.addAudioTrack(source)
		await output.start()
		return output
	}
}

/**
 * Load AAC encoder, if browser can not encode AAC natively with used config
 */
export async function loadAacMediaRecorder() {
	const isSupported = await Promise.all([44_100, 48_000].map((sampleRate) => {
		return canEncodeAudio('aac', { numberOfChannels: NUMBER_OF_CHANNELS, sampleRate, quality: QUALITY })
	}))
	if (isSupported.includes(false)) {
		const { registerAacEncoder } = await import('@mediabunny/aac-encoder')
		registerAacEncoder()
	}
	return AacMediaRecorder
}
