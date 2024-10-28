<template>
	<NcModal v-if="showCallModerationDialog"
		size="small"
		:name="t('spreed', 'Moderate the call')"
		@close="closeModal">
		<div class="call_moderation">
			<h2 class="call_moderation--header nc-dialog-alike-header">
				{{ t('spreed', 'Moderate the call') }}
			</h2>
			<p>
				{{ t('spreed', 'Force the view mode for all participants') }}
			</p>
			<div class="call_view_mode">
				<NcCheckboxRadioSwitch button-variant
					:checked.sync="forcedCallView"
					value="grid"
					name="grid_view_radio"
					type="radio"
					button-variant-grouped="vertical">
					{{ t('spreed', 'Force Grid view') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch button-variant
					:checked.sync="forcedCallView"
					value="speaker"
					name="speaker_view_radio"
					type="radio"
					button-variant-grouped="vertical">
					{{ t('spreed', 'Force Speaker view') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch button-variant
					:checked.sync="forcedCallView"
					value="none"
					name="none_view_radio"
					type="radio"
					button-variant-grouped="vertical">
					{{ t('spreed', 'None') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
	</NcModal>
</template>

<script>

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import { EventBus } from '../../services/EventBus.js'
import { localCallParticipantModel } from '../../utils/webrtc/index.js'

export default {
	name: 'CallModerationDialog',

	components: {
		NcModal,
		NcCheckboxRadioSwitch
	},

	props: {
		showCallModerationDialog: {
			type: Boolean,
			default: false
		}
	},

    emits: ['update:showCallModerationDialog'],

	setup() {
		return {
			localCallParticipantModel,
		}
	},

	data() {
		return {
			forcedCallView: this.localCallParticipantModel.attributes.forcedCallView ?? 'none',
		}
	},

	watch: {
		forcedCallView(value) {
			EventBus.emit('force-call-view-mode', value)
		},
	},

	methods: {
		t,
		closeModal() {
			this.$emit('update:showCallModerationDialog', false)
		}
	}
}
</script>

<style lang="scss" scoped>
.call_moderation {
	padding: calc(var(--default-grid-baseline) * 5);
	.call_view_mode {
		width: fit-content;
	}
}
</style>
