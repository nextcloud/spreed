<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'

import { t } from '@nextcloud/l10n'
import { computed, h } from 'vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconVideoOutline from 'vue-material-design-icons/VideoOutline.vue'
import IconVolumeOutline from '../../../img/material-icons/volume-high-outline.svg?raw'

type NcSelectOption = { id: string | null, label: string }
type MediaDeviceInfoWithFallbackLabel = MediaDeviceInfo & { fallbackLabel: string }

const props = withDefaults(defineProps<{
	kind: 'audioinput' | 'audiooutput' | 'videoinput'
	devices: MediaDeviceInfoWithFallbackLabel[]
	deviceId?: string | null
	enabled?: boolean
}>(), {
	deviceId: undefined,
	enabled: true,
})

const emit = defineEmits<{
	(event: 'refresh'): void
	(event: 'update:deviceId', value: string | null | undefined): void
}>()

const deviceOptions = computed<NcSelectOption[]>(() => {
	const kindDevices = props.devices.filter((device) => device.kind === props.kind)
		.map((device) => ({
			id: device.deviceId,
			label: device.label ? device.label : device.fallbackLabel,
		}))
	if (props.kind === 'audiooutput') {
		return kindDevices
	}
	return [...kindDevices, { id: null, label: t('spreed', 'None') }]
})
const deviceOptionsAvailable = computed(() => deviceOptions.value.length > 1)

const deviceIcon = computed<ComponentPublicInstance | null>(() => {
	switch (props.kind) {
		case 'audioinput': return IconMicrophoneOutline
		case 'audiooutput': return h(NcIconSvgWrapper, {
			svg: IconVolumeOutline,
			size: 20,
		})
		case 'videoinput': return IconVideoOutline
		default: return null
	}
})
const deviceSelectorPlaceholder = computed(() => {
	switch (props.kind) {
		case 'audioinput': return deviceOptionsAvailable.value ? t('spreed', 'Select microphone') : t('spreed', 'No microphone available')
		case 'audiooutput': return deviceOptionsAvailable.value ? t('spreed', 'Select speaker') : t('spreed', 'No speaker available')
		case 'videoinput': return deviceOptionsAvailable.value ? t('spreed', 'Select camera') : t('spreed', 'No camera available')
		default: return ''
	}
})

const deviceSelectedOption = computed<NcSelectOption | null>({
	get: () => {
		return deviceOptions.value.find((option) => option.id === props.deviceId) ?? null
	},
	set: (value) => {
		updateDeviceId(value?.id ?? null)
	},
})

/**
 * Update deviceId if passes required checks
 * @param deviceId selected NcSelect option to update with
 */
function updateDeviceId(deviceId: NcSelectOption['id']) {
	// The deviceSelectedOption may be the same as before yet a change
	// could be triggered if media permissions are granted, which would
	// update the label.
	if (deviceId === props.deviceId) {
		return
	}

	// The previous selected option changed due to the device being
	// disconnected, so ignore it as it was not explicitly changed by
	// the user.
	if (props.deviceId && !deviceOptions.value.find((option) => option.id === props.deviceId)) {
		return
	}

	// Ignore device change on initial loading of the settings dialog.
	if (typeof props.deviceId === 'undefined') {
		return
	}

	emit('update:deviceId', deviceId)
}
</script>

<template>
	<div class="media-devices-selector">
		<component :is="deviceIcon"
			class="media-devices-selector__icon"
			title=""
			:size="20" />

		<NcSelect v-model="deviceSelectedOption"
			:input-id="`device-selector-${props.kind}`"
			:options="deviceOptions"
			label="label"
			:aria-label-combobox="t('spreed', 'Select a device')"
			:clearable="false"
			:placeholder="deviceSelectorPlaceholder"
			:disabled="!enabled || !deviceOptionsAvailable"
			@open="$emit('refresh')" />

		<slot name="extra-action" />
	</div>
</template>

<style lang="scss" scoped>
.media-devices-selector {
	display: flex;
	gap: var(--default-grid-baseline);
	margin: calc(2 * var(--default-grid-baseline)) 0;
	align-items: center;

	&__icon {
		display: flex;
		justify-content: center;
		align-items: center;
		flex-shrink: 0;
		margin-inline-end: var(--default-grid-baseline);
	}

	:deep(.v-select.select) {
		width: 100%;
		margin: 0;
	}

	:deep(.icon-vue) {
		min-width: auto;
		width: 20px;
	}
}
</style>
