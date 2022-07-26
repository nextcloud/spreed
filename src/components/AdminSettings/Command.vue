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
	<Fragment>
		<div class="name">
			{{ name }}
		</div>
		<div class="command">
			{{ command }}
		</div>
		<div class="script">
			{{ script }}
		</div>
		<div class="response">
			{{ translatedResponse }}
		</div>
		<div class="enabled">
			{{ translatedEnabled }}
		</div>
	</Fragment>
</template>

<script>
import { Fragment } from 'vue-frag'

export default {
	name: 'Command',
	components: {
		Fragment,
	},

	props: {
		id: {
			type: Number,
			default: 0,
		},
		name: {
			type: String,
			default: '',
		},
		command: {
			type: String,
			default: '',
		},
		script: {
			type: String,
			default: '',
		},
		response: {
			type: Number,
			default: 0,
		},
		enabled: {
			type: Number,
			default: 0,
		},
	},

	computed: {
		translatedResponse() {
			switch (this.response) {
			case 0:
				return t('spreed', 'None')
			case 1:
				return t('spreed', 'User')
			default:
				return t('spreed', 'Everyone')
			}
		},
		translatedEnabled() {
			switch (this.enabled) {
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
