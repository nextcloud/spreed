<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, reactive } from 'vue'

import IconAccount from 'vue-material-design-icons/Account.vue'
import IconVolumeHigh from 'vue-material-design-icons/VolumeHigh.vue'
import IconVolumeOff from 'vue-material-design-icons/VolumeOff.vue'

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { useStore } from '../../composables/useStore.js'
import { PARTICIPANT } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import type { Conversation } from '../../types/index.ts'

const supportImportantConversations = hasTalkFeature('local', 'important-conversations')
const supportSensitiveConversations = hasTalkFeature('local', 'sensitive-conversations')

const notificationLevels = [
	{ value: PARTICIPANT.NOTIFY.ALWAYS, icon: IconVolumeHigh, label: t('spreed', 'All messages') },
	{ value: PARTICIPANT.NOTIFY.MENTION, icon: IconAccount, label: t('spreed', '@-mentions only') },
	{ value: PARTICIPANT.NOTIFY.NEVER, icon: IconVolumeOff, label: t('spreed', 'Off') },
]

const props = defineProps<{
	conversation: Conversation
}>()

const store = useStore()

const showCallNotificationSettings = computed(() => {
	return !props.conversation.remoteServer || hasTalkFeature(props.conversation.token, 'federation-v2')
})

const loading = reactive({
	level: false,
	calls: false,
	important: false,
	sensitive: false,
})

const notificationLevel = computed(() => props.conversation.notificationLevel.toString())

/**
 * Set the notification level for the conversation
 * FIXME: should be a computed with type "number", but it doesn't work at Vue 2 TS
 *
 * @param value The notification level to set.
 */
async function setNotificationLevel(value: string) {
	loading.level = true
	await store.dispatch('setNotificationLevel', {
		token: props.conversation.token,
		notificationLevel: +value,
	})
	loading.level = false
}

const notifyCalls = computed({
	get: () => props.conversation.notificationCalls === PARTICIPANT.NOTIFY_CALLS.ON,
	set: async (value) => {
		loading.calls = true
		await store.dispatch('setNotificationCalls', {
			token: props.conversation.token,
			notificationCalls: value ? PARTICIPANT.NOTIFY_CALLS.ON : PARTICIPANT.NOTIFY_CALLS.OFF,
		})
		loading.calls = false
	},
})

const isImportant = computed({
	get: () => props.conversation.isImportant,
	set: async (value) => {
		loading.important = true
		await store.dispatch('toggleImportant', {
			token: props.conversation.token,
			isImportant: value,
		})
		loading.important = false
	},
})

const isSensitive = computed({
	get: () => props.conversation.isSensitive,
	set: async (value) => {
		loading.sensitive = true
		await store.dispatch('toggleSensitive', {
			token: props.conversation.token,
			isSensitive: value,
		})
		loading.sensitive = false
	},
})
</script>

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Notifications') }}
		</h4>

		<NcCheckboxRadioSwitch v-for="level in notificationLevels"
			:key="level.value"
			:model-value="notificationLevel"
			:value="level.value.toString()"
			:disabled="loading.level"
			name="notification_level"
			type="radio"
			@update:model-value="setNotificationLevel">
			<span class="radio-button">
				<component :is="level.icon" />
				{{ level.label }}
			</span>
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch v-if="showCallNotificationSettings"
			id="notification_calls"
			v-model="notifyCalls"
			:disabled="loading.calls"
			type="switch">
			{{ t('spreed', 'Notify about calls in this conversation') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch v-if="supportImportantConversations"
			id="important"
			v-model="isImportant"
			:disabled="loading.important"
			aria-describedby="important-hint"
			type="switch">
			{{ t('spreed', 'Important conversation') }}
		</NcCheckboxRadioSwitch>

		<p id="important-hint" class="app-settings-section__hint">
			{{ t('spreed', '"Do not disturb" user status is ignored for important conversations') }}
		</p>

		<NcCheckboxRadioSwitch v-if="supportSensitiveConversations"
			id="sensitive"
			v-model="isSensitive"
			:disabled="loading.sensitive"
			aria-describedby="sensitive-hint"
			type="switch">
			{{ t('spreed', 'Sensitive conversation') }}
		</NcCheckboxRadioSwitch>

		<p id="sensitive-hint" class="app-settings-section__hint">
			{{ t('spreed', 'Message preview will be disabled in conversation list and notifications') }}
		</p>
	</div>
</template>

<style lang="scss" scoped>
.radio-button {
	display: flex;
	align-items: center;
	gap: calc(2 * var(--default-grid-baseline));
}
</style>
