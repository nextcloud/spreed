<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Notifications') }}
		</h4>

		<NcCheckboxRadioSwitch v-for="level in notificationLevels"
			:key="level.value"
			v-model="notificationLevel"
			:value="level.value.toString()"
			name="notification_level"
			type="radio"
			@update:model-value="setNotificationLevel">
			<span class="radio-button">
				<component :is="notificationLevelIcon(level.value)" />
				{{ level.label }}
			</span>
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch v-if="showCallNotificationSettings"
			id="notification_calls"
			v-model="notifyCalls"
			type="switch"
			@update:model-value="setNotificationCalls">
			{{ t('spreed', 'Notify about calls in this conversation') }}
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script>
import Account from 'vue-material-design-icons/Account.vue'
import VolumeHigh from 'vue-material-design-icons/VolumeHigh.vue'
import VolumeOff from 'vue-material-design-icons/VolumeOff.vue'

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { PARTICIPANT } from '../../constants.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'

const notificationLevels = [
	{ value: PARTICIPANT.NOTIFY.ALWAYS, label: t('spreed', 'All messages') },
	{ value: PARTICIPANT.NOTIFY.MENTION, label: t('spreed', '@-mentions only') },
	{ value: PARTICIPANT.NOTIFY.NEVER, label: t('spreed', 'Off') },
]

export default {
	name: 'NotificationsSettings',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},
	},

	setup() {
		return {
			notificationLevels,
		}
	},

	data() {
		return {
			notifyCalls: this.conversation.notificationCalls === PARTICIPANT.NOTIFY_CALLS.ON,
			notificationLevel: this.conversation.notificationLevel.toString(),
		}
	},

	computed: {
		showCallNotificationSettings() {
			return !this.conversation.remoteServer || hasTalkFeature(this.conversation.token, 'federation-v2')
		}
	},

	methods: {
		t,
		notificationLevelIcon(value) {
			switch (value) {
			case PARTICIPANT.NOTIFY.ALWAYS:
				return VolumeHigh
			case PARTICIPANT.NOTIFY.MENTION:
				return Account
			case PARTICIPANT.NOTIFY.NEVER:
			default:
				return VolumeOff
			}
		},

		/**
		 * Set the notification level for the conversation
		 *
		 * @param {number} notificationLevel The notification level to set.
		 */
		async setNotificationLevel(notificationLevel) {
			await this.$store.dispatch('setNotificationLevel', { token: this.conversation.token, notificationLevel })
		},

		/**
		 * Set the call notification level for the conversation
		 *
		 * @param {boolean} isChecked Whether or not call notifications are enabled
		 */
		async setNotificationCalls(isChecked) {
			const notificationCalls = isChecked ? PARTICIPANT.NOTIFY_CALLS.ON : PARTICIPANT.NOTIFY_CALLS.OFF
			await this.$store.dispatch('setNotificationCalls', { token: this.conversation.token, notificationCalls })
		},
	},
}
</script>

<style lang="scss" scoped>
.radio-button {
	display: flex;
	align-items: center;
	gap: calc(2 * var(--default-grid-baseline));
}
</style>
