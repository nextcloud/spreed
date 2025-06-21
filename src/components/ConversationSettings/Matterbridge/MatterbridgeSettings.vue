<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="matterbridge-settings">
		<div v-if="loading" class="loading" />
		<div v-show="!loading">
			<div id="matterbridge-header">
				<p>
					{{ t('spreed', 'You can bridge channels from various instant messaging systems with Matterbridge.') }}
					<a href="https://github.com/42wim/matterbridge/wiki" target="_blank" rel="noopener">
						<span class="icon icon-external" />
						{{ t('spreed', 'More info on Matterbridge') }}
					</a>
				</p>
			</div>
			<div class="basic-settings">
				<div v-show="!enabled"
					class="add-part-wrapper">
					<Plus class="icon" :size="20" />
					<NcSelect label="displayName"
						:aria-label-combobox="t('spreed', 'Messaging systems')"
						:placeholder="newPartPlaceholder"
						:options="options"
						@input="clickAddPart">
						<template #option="option">
							<img class="icon-multiselect-service"
								:src="option.iconUrl"
								alt="">
							{{ option.displayName }}
						</template>
					</NcSelect>
				</div>
				<div v-show="parts.length > 0"
					class="enable-switch-line">
					<NcCheckboxRadioSwitch :model-value="enabled"
						type="switch"
						@update:model-value="onEnabled">
						{{ t('spreed', 'Enable bridge') }}
						({{ processStateText }})
					</NcCheckboxRadioSwitch>
					<NcButton v-if="enabled"
						variant="tertiary"
						:title="t('spreed', 'Show Matterbridge log')"
						:aria-label="t('spreed', 'Show Matterbridge log')"
						@click="showLogContent">
						<template #icon>
							<Message :size="20" />
						</template>
					</NcButton>
					<NcDialog
						v-model:open="logModal"
						:name="t('spreed', 'Log content')"
						size="normal"
						container=".matterbridge-settings"
						close-on-click-outside>
						<NcTextArea :model-value="processLog"
							class="log-content"
							:label="t('spreed', 'Log content')"
							:rows="29"
							readonly
							resize="vertical" />
					</NcDialog>
				</div>
			</div>
			<ul>
				<BridgePart v-for="(part, i) in parts"
					:key="part.type + i"
					:num="i + 1"
					:part="part"
					:type="matterbridgeTypes[part.type]"
					:editing="part.editing"
					:editable="!enabled"
					container=".matterbridge-settings"
					@edit-clicked="onEditClicked(i)"
					@delete-part="onDelete(i)" />
			</ul>
		</div>
	</div>
</template>

<script>
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import Message from 'vue-material-design-icons/Message.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import BridgePart from './BridgePart.vue'
import { useGetToken } from '../../../composables/useGetToken.ts'
import {
	editBridge,
	getBridge,
	getBridgeProcessState,
} from '../../../services/matterbridgeService.js'
import { matterbridgeTypes } from './matterbridgeTypes.ts'

export default {
	name: 'MatterbridgeSettings',
	components: {
		BridgePart,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcSelect,
		NcTextArea,
		// Icons
		Message,
		Plus,
	},

	setup() {
		return {
			matterbridgeTypes,
			token: useGetToken(),
		}
	},

	data() {
		return {
			enabled: false,
			parts: [],
			loading: false,
			processRunning: null,
			processLog: '',
			logModal: false,
			stateLoop: null,
			newPartPlaceholder: t('spreed', 'Add new bridged channel to current conversation'),
		}
	},

	computed: {
		options() {
			return Object.entries(this.matterbridgeTypes).map(([type, value]) => ({
				type,
				displayName: value.name,
				iconUrl: value.iconUrl,
			}))
		},

		processStateText() {
			if (this.processRunning === null) {
				return t('spreed', 'unknown state')
			}

			if (this.processRunning) {
				return t('spreed', 'running')
			} else {
				return this.enabled
					? t('spreed', 'not running, check Matterbridge log')
					: t('spreed', 'not running')
			}
		},
	},

	watch: {
		token: {
			immediate: true,
			handler(token) {
				this.getBridge(token)
				this.relaunchStateLoop(token)
			},
		},
	},

	methods: {
		t,
		relaunchStateLoop(token) {
			// start loop to periodically get bridge state
			clearInterval(this.stateLoop)
			this.stateLoop = setInterval(() => this.getBridgeProcessState(token), 60000)
		},

		clickAddPart(event) {
			const typeKey = event.type
			const type = this.matterbridgeTypes[typeKey]
			const newPart = {
				type: typeKey,
				editing: true,
			}
			for (const fieldKey in type.fields) {
				newPart[fieldKey] = ''
			}
			this.parts.unshift(newPart)
		},

		onDelete(i) {
			this.parts.splice(i, 1)
			this.save()
		},

		onEditClicked(i) {
			this.parts[i].editing = !this.parts[i].editing
			if (!this.parts[i].editing) {
				this.save()
			}
		},

		onEnabled(checked) {
			this.enabled = checked
			this.save()
		},

		save() {
			if (this.parts.length === 0) {
				this.enabled = false
			}
			this.editBridge(this.token, this.enabled, this.parts)
		},

		async getBridge(token) {
			this.loading = true
			try {
				const result = await getBridge(token)
				const bridge = result.data.ocs.data
				this.enabled = bridge.enabled
				this.parts = bridge.parts
				this.processLog = bridge.log
				this.processRunning = bridge.running
			} catch (exception) {
				console.error(exception)
			}
			this.loading = false
		},

		async editBridge() {
			this.loading = true
			this.parts.forEach((part) => {
				part.editing = false
			})
			try {
				const result = await editBridge(this.token, this.enabled, this.parts)
				this.processLog = result.data.ocs.data.log
				this.processRunning = result.data.ocs.data.running
				showSuccess(t('spreed', 'Bridge saved'))
			} catch (exception) {
				console.error(exception)
			}
			this.loading = false
		},

		async getBridgeProcessState(token) {
			try {
				const result = await getBridgeProcessState(token)
				this.processLog = result.data.ocs.data.log
				this.processRunning = result.data.ocs.data.running
			} catch (exception) {
				console.error(exception)
			}
		},

		showLogContent() {
			this.getBridgeProcessState(this.token)
			this.logModal = true
		},
	},
}
</script>

<style lang="scss" scoped>
.icon-multiselect-service {
	width: 16px !important;
	height: 16px !important;
	margin-inline-end: 10px;
	filter: var(--background-invert-if-dark);
}

:deep(.modal-container) {
	height: 700px;
}

.matterbridge-settings {
	.loading {
		margin-top: 30px;
	}

	h3 {
		font-weight: bold;
		padding: 0;
		height: var(--default-clickable-area);
		display: flex;

		p {
			margin-top: auto;
			margin-bottom: auto;
		}

		.icon {
			display: inline-block;
			width: 40px;
		}
		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	#matterbridge-header {
		padding: 0 0 10px 0;

		p {
			color: var(--color-text-maxcontrast);

			a:hover,
			a:focus {
				border-bottom: 2px solid var(--color-text-maxcontrast);
			}

			a .icon {
				display: inline-block;
				margin-bottom: -3px;
			}
		}
	}

	.basic-settings {
		margin-bottom: calc(4 * var(--default-grid-baseline));
		.icon {
			display: inline-flex;
			justify-content: center;
			align-items: center;
			width: var(--default-clickable-area);
			height: var(--default-clickable-area);
		}
		.add-part-wrapper {
			margin-top: 5px;
			display: flex;
			align-items: center;
		}
		.enable-switch-line {
			display: flex;
			height: var(--default-clickable-area);
			margin-top: 5px;
		}
	}

	ul {
		display: flex;
		flex-direction: column;
		gap: calc(4 * var(--default-grid-baseline));
	}
}

.log-content :deep(.textarea__input) {
	height: unset;
}
</style>
