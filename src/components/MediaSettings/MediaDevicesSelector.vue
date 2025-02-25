<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import IconMicrophone from 'vue-material-design-icons/Microphone.vue'
import IconRefresh from 'vue-material-design-icons/Refresh.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'
import IconVolumeHigh from 'vue-material-design-icons/VolumeHigh.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

type NcSelectOption = { id: string | null, label: string }
type MediaDeviceInfoWithFallbackLabel = MediaDeviceInfo & { fallbackLabel: string }

const props = withDefaults(defineProps<{
	kind: 'audioinput' | 'audiooutput' | 'videoinput',
	devices: MediaDeviceInfoWithFallbackLabel[],
	deviceId?: string | null,
	enabled?: boolean,
}>(), {
	deviceId: undefined,
	enabled: true,
})

const emit = defineEmits<{
	(event: 'refresh'): void
	(event: 'update:deviceId', value: string | null | undefined): void
}>()

const deviceSelectedOption = ref<NcSelectOption | null>(null)

const deviceSelectorId = computed(() => 'device-selector-' + props.kind)

const deviceIcon = computed<InstanceType<typeof IconMicrophone> | null>(() => {
	switch (props.kind) {
	case 'audioinput': return IconMicrophone
	case 'audiooutput': return IconVolumeHigh
	case 'videoinput': return IconVideo
	default: return null
	}
})

const deviceOptionsAvailable = computed(() => deviceOptions.value.length > 1)

const deviceSelectorPlaceholder = computed(() => {
	switch (props.kind) {
	case 'audioinput': return audioInputSelectorPlaceholder.value
	case 'audiooutput': return audioOutputSelectorPlaceholder.value
	case 'videoinput': return videoInputSelectorPlaceholder.value
	default: return null
	}
})

const audioInputSelectorPlaceholder = computed(() => {
	if (!deviceOptionsAvailable.value) {
		return t('spreed', 'No microphone available')
	}

	return t('spreed', 'Select microphone')
})

const audioOutputSelectorPlaceholder = computed(() => {
	if (!deviceOptionsAvailable.value) {
		return t('spreed', 'No speaker available')
	}

	return t('spreed', 'Select speaker')
})

const videoInputSelectorPlaceholder = computed(() => {
	if (!deviceOptionsAvailable.value) {
		return t('spreed', 'No camera available')
	}

	return t('spreed', 'Select camera')
})

const deviceOptions = computed(() => {
	const options: NcSelectOption[] = props.devices.filter(device => device.kind === props.kind).map(device => {
		return {
			id: device.deviceId,
			label: device.label ? device.label : device.fallbackLabel,
		}
	})

	options.push({
		id: null,
		label: t('spreed', 'None'),
	})

	return options
})

const deviceSelectedOptionFromDeviceId = computed(() => {
	return deviceOptions.value.find(option => option.id === props.deviceId)
})

// The watcher needs to be set as "immediate" to ensure that
// "deviceSelectedOption" will be set when mounted.
watch(deviceSelectedOptionFromDeviceId, (value) => {
	deviceSelectedOption.value = value ?? null
}, { immediate: true })

// The watcher should not be set as "immediate" to prevent
// "update:deviceId" from being emitted when mounted with the same value
// initially passed to the component.
watch(deviceSelectedOption, (deviceSelectedOption, previousSelectedOption) => {
	// The deviceSelectedOption may be the same as before yet a change
	// could be triggered if media permissions are granted, which would
	// update the label.
	if (deviceSelectedOption && previousSelectedOption && deviceSelectedOption.id === previousSelectedOption.id) {
		return
	}

	// The previous selected option changed due to the device being
	// disconnected, so ignore it as it was not explicitly changed by
	// the user.
	if (previousSelectedOption && previousSelectedOption.id && !deviceOptions.value.find(option => option.id === previousSelectedOption.id)) {
		return
	}

	// Ignore device change on initial loading of the settings dialog.
	if (typeof previousSelectedOption?.id === 'undefined') {
		return
	}

	if (deviceSelectedOption && deviceSelectedOption.id === null) {
		emit('update:deviceId', null)
		return
	}

	emit('update:deviceId', deviceSelectedOption ? deviceSelectedOption.id : undefined)
})
</script>

<template>
	<div class="media-devices-selector">
		<component :is="deviceIcon"
			class="media-devices-selector__icon"
			title=""
			:size="16" />

		<NcSelect v-model="deviceSelectedOption"
			:input-id="deviceSelectorId"
			:options="deviceOptions"
			label="label"
			:aria-label-combobox="t('spreed', 'Select a device')"
			:clearable="false"
			:placeholder="deviceSelectorPlaceholder"
			:disabled="!enabled || !deviceOptionsAvailable" />

		<NcButton type="tertiary"
			:title="t('spreed', 'Refresh devices list')"
			:aria-lebel="t('spreed', 'Refresh devices list')"
			@click="$emit('refresh')">
			<IconRefresh :size="20" />
		</NcButton>
	</div>
</template>

<style lang="scss" scoped>
.media-devices-selector {
	display: flex;
	gap: var(--default-grid-baseline);
	margin: calc(3 * var(--default-grid-baseline)) 0;

	&__icon {
		display: flex;
		justify-content: center;
		align-items: center;
		width: var(--default-clickable-area);
		flex-shrink: 0;
	}

	:deep(.v-select.select) {
		width: 100%;
	}
}
</style>
