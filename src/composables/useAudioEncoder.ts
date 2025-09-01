/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DeepReadonly, Ref } from 'vue'

import { register } from 'extendable-media-recorder'
import { connect } from 'extendable-media-recorder-wav-encoder'
import { readonly, ref } from 'vue'

let requiresInit = true
const encoderReady = ref(false)

/**
 * Initialize the audio encoder
 */
async function initAudioEncoder() {
	requiresInit = false
	await register(await connect())
	encoderReady.value = true
}

/**
 * Composable to use audio encoder for voice messages feature
 *
 * @return {DeepReadonly<Ref<boolean>>} - whether the encoder is ready
 */
export function useAudioEncoder(): DeepReadonly<Ref<boolean>> {
	if (requiresInit) {
		initAudioEncoder()
	}

	return readonly(encoderReady)
}
