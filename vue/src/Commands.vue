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
	<div id="chat_commands" class="section">
		<h2>{{ t('spreed', 'Commands') }}</h2>

		<div>
			<command v-for="command in commands" v-bind="command" :key="command.id"></command>
		</div>
	</div>
</template>

<script>
	import axios from 'nextcloud-axios';
	import Command from './components/Command';

	export default {
		name: 'app',

		data () {
			return {
				commands: {}
			}
		},

		components: {
			Command
		},

		mounted () {
			axios
				.get(OC.linkToOCS('apps/spreed/api/v1', 2) + 'command')
				.then(response => {
					if (response.data.ocs.data.length !== 0) {
						this.commands = response.data.ocs.data;
					}
				});
		}
	}
</script>
