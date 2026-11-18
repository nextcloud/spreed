<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useDevices } from '../../composables/useDevices.js'

type DeviceStatus = 'off' | 'pending' | 'error' | 'ok'
type Status = DeviceStatus | 'prompt'
type StreamError = { name: string, message?: string }

const {
	devices,
	audioInputId,
	videoInputId,
	audioStream,
	videoStream,
	audioStreamError,
	videoStreamError,
	audioStreamPending,
	videoStreamPending,
} = useDevices()

const microphonePermission = ref<PermissionState>()
const cameraPermission = ref<PermissionState>()
const permissionStatuses: PermissionStatus[] = []

queryPermission('microphone', microphonePermission)
queryPermission('camera', cameraPermission)

onBeforeUnmount(() => {
	permissionStatuses.forEach((status) => {
		status.onchange = null
	})
})

const audioStatus = computed(() => getDeviceStatus('audioinput', audioInputId.value, audioStream.value, audioStreamError.value, audioStreamPending.value))
const videoStatus = computed(() => getDeviceStatus('videoinput', videoInputId.value, videoStream.value, videoStreamError.value, videoStreamPending.value))

const isMicrophonePrompt = computed(() => audioStatus.value === 'pending' && microphonePermission.value === 'prompt')
const isCameraPrompt = computed(() => videoStatus.value === 'pending' && cameraPermission.value === 'prompt')

const status = computed<Status>(() => {
	const statuses = [audioStatus.value, videoStatus.value]
	if (statuses.includes('pending')) {
		return (isMicrophonePrompt.value || isCameraPrompt.value) ? 'prompt' : 'pending'
	}
	if (statuses.includes('error')) {
		return 'error'
	}
	if (statuses.includes('ok')) {
		return 'ok'
	}
	return 'off'
})

const promptText = computed(() => {
	if (isMicrophonePrompt.value && isCameraPrompt.value) {
		return t('spreed', 'Allow access to your microphone and camera in the browser')
	}
	if (isMicrophonePrompt.value) {
		return t('spreed', 'Allow access to your microphone in the browser')
	}
	return t('spreed', 'Allow access to your camera in the browser')
})

const errorMessages = computed(() => [
	audioStatus.value === 'error' ? getAudioErrorMessage(audioStreamError.value) : null,
	videoStatus.value === 'error' ? getVideoErrorMessage(videoStreamError.value) : null,
].filter(Boolean))

const statusMessages = computed(() => {
	switch (status.value) {
		case 'prompt':
			return [promptText.value]
		case 'pending':
			return [t('spreed', 'Configuring devices …')]
		case 'error':
			return errorMessages.value
		case 'ok':
			return [t('spreed', 'Your devices are working properly')]
		default:
			return [t('spreed', 'No microphone or camera available')]
	}
})

/**
 * Query the browser permission state and keep it updated
 *
 * @param name permission name
 * @param state ref to update
 */
async function queryPermission(name: string, state: typeof microphonePermission) {
	try {
		const permissionStatus = await navigator.permissions.query({ name: name as PermissionName })
		state.value = permissionStatus.state
		permissionStatus.onchange = () => {
			state.value = permissionStatus.state
		}
		permissionStatuses.push(permissionStatus)
	} catch {
		// Permission name is not supported by the browser
	}
}

/**
 * Get the status of a single input device
 *
 * @param kind device kind
 * @param inputId selected device id (null - disabled, undefined - not selected yet)
 * @param stream current stream
 * @param error error of the last getUserMedia call
 * @param pending whether getUserMedia is in progress
 */
function getDeviceStatus(kind: 'audioinput' | 'videoinput', inputId: string | null | undefined, stream: MediaStream | null, error: StreamError | null, pending: boolean): DeviceStatus {
	if (inputId === null) {
		return 'off'
	}
	if (pending) {
		return 'pending'
	}
	if (error) {
		return 'error'
	}
	if (stream) {
		return 'ok'
	}
	if (inputId === undefined && devices.value.length && !devices.value.some((device: MediaDeviceInfo) => device.kind === kind)) {
		return 'off'
	}
	return 'pending'
}

/**
 * Whether the error is caused by an insecure context (HTTP)
 *
 * @param error stream error
 */
function isInsecureContextError(error: StreamError) {
	// In newer browser versions MediaDevicesManager is not supported in
	// insecure contexts; in older browser versions it is, but getting
	// the user media fails with "NotAllowedError".
	const isInsecureContext = 'isSecureContext' in window && !window.isSecureContext
	return (error.name === 'NotSupportedError' && isInsecureContext)
		|| (error.name === 'NotAllowedError' && !!error.message?.includes('Only secure origins'))
}

/**
 * Get the error message for the microphone
 *
 * @param error stream error
 */
function getAudioErrorMessage(error: StreamError | null) {
	if (!error) {
		return null
	}
	if (error.name === 'NotSupportedError' && !window.RTCPeerConnection) {
		return t('spreed', 'Calls are not supported in your browser')
	}
	if (isInsecureContextError(error)) {
		return t('spreed', 'Access to microphone is only possible with HTTPS')
	}
	if (error.name === 'NotAllowedError') {
		return t('spreed', 'Access to microphone was denied')
	}
	return t('spreed', 'Error while accessing microphone')
}

/**
 * Get the error message for the camera
 *
 * @param error stream error
 */
function getVideoErrorMessage(error: StreamError | null) {
	if (!error) {
		return null
	}
	if (error.name === 'NotSupportedError' && !window.RTCPeerConnection) {
		return t('spreed', 'Calls are not supported in your browser')
	}
	if (isInsecureContextError(error)) {
		return t('spreed', 'Access to camera is only possible with HTTPS')
	}
	if (error.name === 'NotAllowedError') {
		return t('spreed', 'Access to camera was denied')
	}
	return t('spreed', 'Error while accessing camera')
}
</script>

<template>
	<p
		class="media-devices-status"
		role="status">
		<span
			class="media-devices-status__indicator"
			:class="`media-devices-status__indicator--${status}`" />
		<span>
			<span
				v-for="message in statusMessages"
				:key="message"
				class="media-devices-status__message">
				{{ message }}
			</span>
		</span>
	</p>
</template>

<style lang="scss" scoped>
.media-devices-status {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block: calc(var(--default-grid-baseline) * 4) 0;

	&__indicator {
		flex-shrink: 0;
		width: calc(var(--default-grid-baseline) * 3);
		height: calc(var(--default-grid-baseline) * 3);
		border-radius: 50%;
		background-color: var(--color-text-maxcontrast);

		&--ok {
			background-color: var(--color-element-success);
		}

		&--prompt {
			background-color: var(--color-element-warning);
		}

		&--error {
			background-color: var(--color-element-error);
		}
	}

	&__message {
		display: block;
	}
}
</style>
