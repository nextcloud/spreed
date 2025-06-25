<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { InitialState } from '../types/index.ts'

import { loadState } from '@nextcloud/initial-state'
import { computed, ref } from 'vue'
import AllowedGroups from '../components/AdminSettings/AllowedGroups.vue'
import BotsSettings from '../components/AdminSettings/BotsSettings.vue'
import Federation from '../components/AdminSettings/Federation.vue'
import GeneralSettings from '../components/AdminSettings/GeneralSettings.vue'
import HostedSignalingServer from '../components/AdminSettings/HostedSignalingServer.vue'
import MatterbridgeIntegration from '../components/AdminSettings/MatterbridgeIntegration.vue'
import RecordingServers from '../components/AdminSettings/RecordingServers.vue'
import SignalingServers from '../components/AdminSettings/SignalingServers.vue'
import SIPBridge from '../components/AdminSettings/SIPBridge.vue'
import StunServers from '../components/AdminSettings/StunServers.vue'
import TurnServers from '../components/AdminSettings/TurnServers.vue'
import WebServerSetupChecks from '../components/AdminSettings/WebServerSetupChecks.vue'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'

const hasValidSubscription = loadState<InitialState['spreed']['has_valid_subscription']>('spreed', 'has_valid_subscription')

const supportFederation = hasTalkFeature('local', 'federation-v1')

const signalingServers = ref<InitialState['spreed']['signaling_servers']>(loadState('spreed', 'signaling_servers', {
	hideWarning: false,
	secret: '',
	servers: [],
}))
const hasSignalingServers = computed(() => signalingServers.value.servers.length > 0)
</script>

<template>
	<div>
		<SignalingServers
			v-model:servers="signalingServers.servers"
			v-model:secret="signalingServers.secret"
			v-model:hide-warning="signalingServers.hideWarning"
			:has-valid-subscription="hasValidSubscription" />
		<HostedSignalingServer :has-signaling-servers="hasSignalingServers" />
		<GeneralSettings :has-signaling-servers="hasSignalingServers" />
		<AllowedGroups />
		<Federation v-if="supportFederation" />
		<BotsSettings />
		<WebServerSetupChecks />
		<StunServers />
		<TurnServers />
		<RecordingServers :has-signaling-servers="hasSignalingServers" />
		<SIPBridge :has-signaling-servers="hasSignalingServers" />
		<MatterbridgeIntegration />
	</div>
</template>
