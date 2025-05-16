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

		<NcCheckboxRadioSwitch v-if="supportImportantConversations"
			id="important"
			v-model="isImportant"
			aria-describedby="important-hint"
			type="switch"
			@update:model-value="toggleImportant">
			{{ t('spreed', 'Important conversation') }}
		</NcCheckboxRadioSwitch>

		<p id="important-hint" class="app-settings-section__hint">
			{{ t('spreed', '"Do not disturb" user status is ignored for important conversations') }}
		</p>

		<NcCheckboxRadioSwitch v-if="supportSensitiveConversations"
			id="sensitive"
			v-model="isSensitive"
			aria-describedby="sensitive-hint"
			type="switch"
			@update:model-value="toggleSensitive">
			{{ t('spreed', 'Sensitive conversation') }}
		</NcCheckboxRadioSwitch>

		<p id="sensitive-hint" class="app-settings-section__hint">
			{{ t('spreed', 'Message preview will be disabled in conversation list and notifications') }}
		</p>
	</div>
</template>

<script>
import Account from 'vue-material-design-icons/Account.vue'
import VolumeHigh from 'vue-material-design-icons/VolumeHigh.vue'
import VolumeOff from 'vue-material-design-icons/VolumeOff.vue'

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { PARTICIPANT } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'

const supportImportantConversations = hasTalkFeature('local', 'important-conversations')
const supportSensitiveConversations = hasTalkFeature('local', 'sensitive-conversations')

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
			supportImportantConversations,
			supportSensitiveConversations,
		}
	},

	data() {
		return {
			notifyCalls: this.conversation.notificationCalls === PARTICIPANT.NOTIFY_CALLS.ON,
			notificationLevel: this.conversation.notificationLevel.toString(),
			isImportant: this.conversation.isImportant,
			isSensitive: this.conversation.isSensitive,
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

		/**
		 * Toggle the important flag for the conversation
		 *
		 * @param {boolean} isImportant The important flag to set.
		 */
		async toggleImportant(isImportant) {
			await this.$store.dispatch('toggleImportant', { token: this.conversation.token, isImportant })
		},

		/**
		 * Toggle the sensitive flag for the conversation
		 *
		 * @param {boolean} isSensitive The sensitive flag to set.
		 */
		async toggleSensitive(isSensitive) {
			await this.$store.dispatch('toggleSensitive', { token: this.conversation.token, isSensitive })
		}
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
