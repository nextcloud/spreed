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
	<div id="chat_commands" class="commands section">
		<h2>{{ t('spreed', 'Commands') }}</h2>

		<div id="commands_list">
			<div class="head name">
				{{ t('spreed', 'Name') }}
			</div>
			<div class="head command">
				{{ t('spreed', 'Command') }}
			</div>
			<div class="head script">
				{{ t('spreed', 'Script') }}
			</div>
			<div class="head response">
				{{ t('spreed', 'Response to') }}
			</div>
			<div class="head enabled">
				{{ t('spreed', 'Enabled for') }}
			</div>
			<command v-for="command in commands" :key="command.id" v-bind="command" />
		</div>
	</div>
</template>

<script>
import Command from './components/Command'

export default {
	name: 'App',

	components: {
		Command
	},

	data() {
		return {
			commands: {}
		}
	},

	mounted() {
		this.commands = OCP.InitialState.loadState('talk', 'commands')
	}
}
</script>

<style lang="scss" scoped>
.commands.section {
	#commands_list {
		display: grid;
		grid-template-columns: minmax(100px, 200px) minmax(100px, 200px)  1fr minmax(100px, 200px)  minmax(100px, 200px);
		grid-column-gap: 5px;
		grid-row-gap: 10px;
		.head {
			padding-bottom: 5px;
			border-bottom: 1px solid var(--color-border);
			font-weight: bold;
		}
	}
}
</style>
