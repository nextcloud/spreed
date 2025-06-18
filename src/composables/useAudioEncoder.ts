/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useAsyncInit } from './useAsyncInit.ts'

/**
 * Initialize the audio encoder
 */
async function initAudioEncoder() {
	const { register, MediaRecorder } = await import('extendable-media-recorder')
	const { connect } = await import('extendable-media-recorder-wav-encoder')
	await register(await connect())
	return MediaRecorder
}

/**
 * Composable to use audio encoder for voice messages feature
 *
 * @return - whether the encoder is ready
 */
export function useAudioEncoder() {
	const {
		isReady: isMediaRecorderReady,
		isLoading: isMediaRecorderLoading,
		result: MediaRecorder,
		init: initMediaRecorder,
	} = useAsyncInit(initAudioEncoder)

	return {
		isMediaRecorderReady,
		isMediaRecorderLoading,
		MediaRecorder,
		initMediaRecorder,
	}
}
