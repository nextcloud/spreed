<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { VIRTUAL_BACKGROUND } from '../../constants.ts'
import VideoStreamBackgroundEffect from '../../utils/media/effects/virtual-background/VideoStreamBackgroundEffect.js'
import VirtualBackground from '../../utils/media/pipeline/VirtualBackground.js'

const apachePHPConfiguration = loadState<string>('spreed', 'valid_apache_php_configuration')

const virtualBackgroundAvailable = ref<boolean | undefined>(undefined)

const virtualBackgroundAvailableAriaLabel = computed(() => {
	if (virtualBackgroundAvailable.value === false) {
		return t('spreed', 'Failed')
	}

	if (virtualBackgroundAvailable.value === true) {
		return t('spreed', 'OK')
	}

	return t('spreed', 'Checking …')
})

const virtualBackgroundAvailableTitle = computed(() => {
	if (virtualBackgroundAvailable.value === false && !VirtualBackground.isWasmSupported()) {
		return t('spreed', 'Failed: WebAssembly is disabled or not supported in this browser. Please enable WebAssembly or use a browser with support for it to do the check.')
	}

	if (virtualBackgroundAvailable.value === false) {
		return t('spreed', 'Failed: ".wasm" and ".tflite" files were not properly returned by the web server. Please check "System requirements" section in Talk documentation.')
	}

	if (virtualBackgroundAvailable.value === true) {
		return t('spreed', 'OK: ".wasm" and ".tflite" files were properly returned by the web server.')
	}

	return t('spreed', 'Checking …')
})

const apacheWarning = computed(() => {
	if (apachePHPConfiguration === 'invalid') {
		return t('spreed', 'It seems that the PHP and Apache configuration is not compatible. Please note that PHP can only be used with the MPM_PREFORK module and PHP-FPM can only be used with the MPM_EVENT module.')
	}
	// Disabling this for now as there were too many false catches (VMs, AIO, nginx, permissions issue, …)
	// if (apachePHPConfiguration === 'unknown') {
	// return t('spreed', 'Could not detect the PHP and Apache configuration because exec is disabled or apachectl is not working as expected. Please note that PHP can only be used with the MPM_PREFORK module and PHP-FPM can only be used with the MPM_EVENT module.')
	// }
	return ''
})

const apacheWarningType = computed(() => {
	if (apachePHPConfiguration === 'invalid') {
		return 'error'
	}
	return 'warning'
})

checkVirtualBackground()

/**
 * Check if the files required for virtual background can be loaded
 */
function checkVirtualBackground() {
	if (!VirtualBackground.isWasmSupported()) {
		virtualBackgroundAvailable.value = false

		return
	}

	virtualBackgroundAvailable.value = undefined

	// Pass only the essential options to check if the files can be
	// loaded.
	const options = {
		virtualBackground: {
			type: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR,
		},

		webGL: VirtualBackground.isWebGLSupported(),
	}

	// Width and height are not needed to only load the files
	const videoStreamBackgroundEffect = new VideoStreamBackgroundEffect(options as ConstructorParameters<typeof VideoStreamBackgroundEffect>[0])
	videoStreamBackgroundEffect.load().then(() => {
		virtualBackgroundAvailable.value = true
	}).catch(() => {
		virtualBackgroundAvailable.value = false
	})
}
</script>

<template>
	<div id="web_server_setup_checks" class="section">
		<h2>
			{{ t('spreed', 'Web server setup checks') }}
		</h2>

		<NcNoteCard v-if="apacheWarning" :type="apacheWarningType" :text="apacheWarning" />

		<ul class="web-server-setup-checks">
			<li class="virtual-background">
				{{ t('spreed', 'Files required for virtual background can be loaded') }}
				<NcButton
					variant="tertiary"
					class="vue-button-inline"
					:title="virtualBackgroundAvailableTitle"
					:aria-label="virtualBackgroundAvailableAriaLabel"
					@click="checkVirtualBackground">
					<template #icon>
						<IconAlertCircleOutline v-if="virtualBackgroundAvailable === false" :size="20" fillColor="var(--color-border-error)" />
						<IconCheck v-else-if="virtualBackgroundAvailable === true" :size="20" fillColor="var(--color-border-success)" />
						<NcLoadingIcon v-else :size="20" />
					</template>
				</NcButton>
			</li>
		</ul>
	</div>
</template>

<style lang="scss" scoped>
.vue-button-inline {
	display: inline-block !important;
}
</style>
