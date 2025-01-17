<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<GeneralSettings />
		<AllowedGroups />
		<Federation v-if="supportFederation" />
		<BotsSettings />
		<WebServerSetupChecks />
		<StunServers />
		<TurnServers />
		<SignalingServers />
		<HostedSignalingServer />
		<RecordingServers />
		<SIPBridge />
		<MatterbridgeIntegration />
	</div>
</template>

<script>
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

const supportFederation = hasTalkFeature('local', 'federation-v1')

export default {
	name: 'AdminSettings',

	components: {
		AllowedGroups,
		BotsSettings,
		Federation,
		GeneralSettings,
		HostedSignalingServer,
		MatterbridgeIntegration,
		RecordingServers,
		SignalingServers,
		SIPBridge,
		StunServers,
		TurnServers,
		WebServerSetupChecks,
	},

	setup() {
		return { supportFederation }
	}
}
</script>
