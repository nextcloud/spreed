<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
 -
 - @license AGPL-3.0-or-later
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
	<section id="chat_commands" class="commands section">
		<h2>
			{{ t('spreed', 'Commands') }}
			<small>{{ t('spreed', 'Deprecated') }}</small>
		</h2>

		<!-- eslint-disable-next-line vue/no-v-html -->
		<p class="settings-hint" v-html="commandHint" />

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

			<template v-for="command in commands">
				<div :key="`${command.id}_name`" class="name">
					{{ command.name }}
				</div>
				<div :key="`${command.id}_command`" class="command">
					{{ command.command }}
				</div>
				<div :key="`${command.id}_script`" class="script">
					{{ command.script }}
				</div>
				<div :key="`${command.id}_response`" class="response">
					{{ translateResponse(command.response) }}
				</div>
				<div :key="`${command.id}_enabled`" class="enabled">
					{{ translateEnabled(command.enabled) }}
				</div>
			</template>
		</div>
	</section>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'Commands',

	data() {
		return {
			commands: {},
		}
	},

	computed: {
		commandHint() {
			return t('spreed', 'Commands are a new beta feature in Nextcloud Talk. They allow you to run scripts on your Nextcloud server. You can define them with our command line interface. An example of a calculator script can be found in our {linkstart}documentation{linkend}.')
				.replace('{linkstart}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://nextcloud-talk.readthedocs.io/en/latest/commands/">')
				.replace('{linkend}', ' ↗</a>')
		},
	},

	mounted() {
		this.commands = loadState('spreed', 'commands')
	},

	methods: {
		translateResponse(response) {
			switch (response) {
			case 0:
				return t('spreed', 'None')
			case 1:
				return t('spreed', 'User')
			default:
				return t('spreed', 'Everyone')
			}
		},
		translateEnabled(enabled) {
			switch (enabled) {
			case 0:
				return t('spreed', 'Disabled')
			case 1:
				return t('spreed', 'Moderators')
			case 2:
				return t('spreed', 'Users')
			default:
				return t('spreed', 'Everyone')
			}
		},
	},
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

	small {
		color: var(--color-warning);
		border: 1px solid var(--color-warning);
		border-radius: 16px;
		padding: 0 9px;
	}
}
</style>
