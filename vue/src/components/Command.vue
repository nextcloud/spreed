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
		<input type="text" class="name" placeholder="t('spreed', 'Poster name')" :value="name" :aria-label="t('spreed', 'Poster name')">
		<input type="text" class="pattern" placeholder="t('spreed', 'Command pattern (e.g. `^help` to match all messages starting with help)')" :value="pattern" :aria-label="t('spreed', 'Command pattern')">
		<input type="text" class="script" placeholder="/path/to/your/script" :value="script" :aria-label="t('spreed', 'Script to execute')">

		<multiselect
			:value="selectedOutput"
			:options="outputOptions"
			:placeholder="t('spreed', 'Response visibility')"
			label="label"
			track-by="value"
			@input="updateOutput" />
	</div>
</template>

<script>
	import { Multiselect } from 'nextcloud-vue';

	export default {
		name: 'command',

		props: [
			'id',
			'name',
			'pattern',
			'script',
			'output'
		],

		computed: {
			selectedOutput() {
				return this.outputOptions.find(option => option.value === this.output)
			},
			outputOptions () {
				return [
					{ label: t('spreed', 'None'), value: 0 },
					{ label: t('spreed', 'User'), value: 1 },
					{ label: t('spreed', 'Everyone'), value: 2 },
				];
			}
		},

		methods: {
			updateOutput: option => {
				console.debug(option)
				// send the option.value change to the database
			}
		},

		components: {
			Multiselect
		}
	}
</script>
