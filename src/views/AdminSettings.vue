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
		<Commands />
		<StunServers />
		<TurnServers />
		<SignalingServers />
		<HostedSignalingServer />
	</div>
</template>

<script>
import GeneralSettings from '../components/AdminSettings/GeneralSettings'
import AllowedGroups from '../components/AdminSettings/AllowedGroups'
import Commands from '../components/AdminSettings/Commands'
import StunServers from '../components/AdminSettings/StunServers'
import TurnServers from '../components/AdminSettings/TurnServers'
import SignalingServers from '../components/AdminSettings/SignalingServers'
import HostedSignalingServer from '../components/AdminSettings/HostedSignalingServer'
import MatterbridgeIntegration from '../components/AdminSettings/MatterbridgeIntegration'

export default {
	name: 'AdminSettings',

	components: {
		GeneralSettings,
		AllowedGroups,
		Commands,
		StunServers,
		TurnServers,
		SignalingServers,
		HostedSignalingServer,
		MatterbridgeIntegration,
	},
}
</script>
