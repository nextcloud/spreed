<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<div>
		<GeneralSettings />
		<MatterbridgeIntegration />
		<AllowedGroups />
		<!-- TODO remove OC.debug when Federation feature is ready -->
		<Federation v-if="OC.debug" />
		<BotsSettings />
		<Commands />
		<WebServerSetupChecks />
		<StunServers />
		<TurnServers />
		<SignalingServers />
		<HostedSignalingServer />
		<RecordingServers />
		<SIPBridge />
	</div>
</template>

<script>
import AllowedGroups from '../components/AdminSettings/AllowedGroups.vue'
import BotsSettings from '../components/AdminSettings/BotsSettings.vue'
import Commands from '../components/AdminSettings/Commands.vue'
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

export default {
	name: 'AdminSettings',

	components: {
		AllowedGroups,
		BotsSettings,
		Commands,
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
}
</script>
