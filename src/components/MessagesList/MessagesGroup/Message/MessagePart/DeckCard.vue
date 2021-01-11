<!--
  - @copyright Copyright (c) 2020, Joas Schilling <coding@schilljs.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<a
		v-tooltip.bottom="deckLocation"
		class="external"
		:href="link"
		target="_blank">
		<strong>{{ name }}</strong>
	</a>
</template>

<script>
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

export default {
	name: 'DeckCard',

	directives: {
		tooltip: Tooltip,
	},

	props: {
		type: {
			type: String,
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		boardname: {
			type: String,
			required: true,
		},
		stackname: {
			type: String,
			required: true,
		},
		link: {
			type: String,
			required: true,
		},
	},

	computed: {
		deckLocation() {
			return t('spreed', '{stack} in {board}', {
				stack: this.stackname,
				board: this.boardname,
			})
		},
	},
}
</script>
