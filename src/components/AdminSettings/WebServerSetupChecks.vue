<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="web_server_setup_checks" class="section">
		<h2>
			{{ t('spreed', 'Web server setup checks') }}
		</h2>

		<NcNoteCard v-if="apacheWarning" :type="apacheWarningType" :text="apacheWarning" />

		<ul class="web-server-setup-checks">
			<li class="virtual-background">
				{{ t('spreed', 'Files required for virtual background can be loaded') }}
				<NcButton variant="tertiary"
					class="vue-button-inline"
					:class="{ 'success-button': virtualBackgroundAvailable === true, 'error-button': virtualBackgroundAvailable === false }"
					:title="virtualBackgroundAvailableTitle"
					:aria-label="virtualBackgroundAvailableAriaLabel"
					@click="checkVirtualBackground">
					<template #icon>
						<IconAlertCircleOutline v-if="virtualBackgroundAvailable === false" :size="20" />
						<IconCheck v-else-if="virtualBackgroundAvailable === true" :size="20" />
						<NcLoadingIcon v-else :size="20" />
					</template>
				</NcButton>
			</li>
		</ul>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { VIRTUAL_BACKGROUND } from '../../constants.ts'
import JitsiStreamBackgroundEffect from '../../utils/media/effects/virtual-background/JitsiStreamBackgroundEffect.js'
import VirtualBackground from '../../utils/media/pipeline/VirtualBackground.js'

export default {
	name: 'WebServerSetupChecks',

	components: {
		NcLoadingIcon,
		IconAlertCircleOutline,
		NcButton,
		NcNoteCard,
		IconCheck,
	},

	data() {
		return {
			virtualBackgroundLoaded: undefined,
			apachePHPConfiguration: '',
		}
	},

	computed: {
		virtualBackgroundAvailable() {
			return this.virtualBackgroundLoaded
		},

		virtualBackgroundAvailableAriaLabel() {
			if (this.virtualBackgroundAvailable === false) {
				return t('spreed', 'Failed')
			}

			if (this.virtualBackgroundAvailable === true) {
				return t('spreed', 'OK')
			}

			return t('spreed', 'Checking …')
		},

		virtualBackgroundAvailableTitle() {
			if (this.virtualBackgroundAvailable === false && !VirtualBackground.isWasmSupported()) {
				return t('spreed', 'Failed: WebAssembly is disabled or not supported in this browser. Please enable WebAssembly or use a browser with support for it to do the check.')
			}

			if (this.virtualBackgroundAvailable === false) {
				return t('spreed', 'Failed: ".wasm" and ".tflite" files were not properly returned by the web server. Please check "System requirements" section in Talk documentation.')
			}

			if (this.virtualBackgroundAvailable === true) {
				return t('spreed', 'OK: ".wasm" and ".tflite" files were properly returned by the web server.')
			}

			return t('spreed', 'Checking …')
		},

		apacheWarning() {
			if (this.apachePHPConfiguration === 'invalid') {
				return t('spreed', 'It seems that the PHP and Apache configuration is not compatible. Please note that PHP can only be used with the MPM_PREFORK module and PHP-FPM can only be used with the MPM_EVENT module.')
			}
			// Disabling this for now as there were too many false catches (VMs, AIO, nginx, permissions issue, …)
			// if (this.apachePHPConfiguration === 'unknown') {
			// return t('spreed', 'Could not detect the PHP and Apache configuration because exec is disabled or apachectl is not working as expected. Please note that PHP can only be used with the MPM_PREFORK module and PHP-FPM can only be used with the MPM_EVENT module.')
			// }
			return ''
		},

		apacheWarningType() {
			if (this.apachePHPConfiguration === 'invalid') {
				return 'error'
			}
			return 'warning'
		},
	},

	mounted() {
		this.apachePHPConfiguration = loadState('spreed', 'valid_apache_php_configuration')
	},

	beforeMount() {
		this.checkVirtualBackground()
	},

	methods: {
		t,
		checkVirtualBackground() {
			if (!VirtualBackground.isWasmSupported()) {
				this.virtualBackgroundLoaded = false

				return
			}

			this.virtualBackgroundLoaded = undefined

			// Pass only the essential options to check if the files can be
			// loaded.
			const options = {
				virtualBackground: {
					type: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR,
				},

				simd: VirtualBackground.isWasmSimd(),
			}

			// When the worker is loaded from Talk its URL starts with
			// "apps/spreed/js". However, when it is loaded from the
			// administration settings its URL starts with "apps/talk/js"
			// instead, so it fails to load.
			//
			// "publicPath" option in "worker-loader" configuration does not
			// work with Webpack 5. As a workaround the public path needs to be
			// overriden at runtime before loading the worker and restored
			// afterwards.
			// https://github.com/webpack-contrib/worker-loader/issues/281
			const __webpack_public_path__saved = __webpack_public_path__

			__webpack_public_path__ = generateFilePath('spreed', 'js', '')

			const jitsiStreamBackgroundEffect = new JitsiStreamBackgroundEffect(options)

			__webpack_public_path__ = __webpack_public_path__saved

			jitsiStreamBackgroundEffect.load().then(() => {
				this.virtualBackgroundLoaded = true
			}).catch(() => {
				this.virtualBackgroundLoaded = false
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.vue-button-inline {
	display: inline-block !important;

	&.success-button {
		color: var(--color-success);
	}

	&.error-button {
		color: var(--color-error);
	}
}
</style>
