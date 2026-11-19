/*!
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { useAsyncInit } from './useAsyncInit.ts'

/**
 * Initialize the audio encoder
 */
async function initAudioEncoder() {
	const { loadAacMediaRecorder } = await import('../utils/media/AacMediaRecorder.ts')
	return loadAacMediaRecorder()
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
