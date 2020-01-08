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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<span v-html="text" />
</template>

<script>
import escapeHtml from 'escape-html'

export default {
	name: 'PlainText',
	props: {
		data: {
			type: Object,
			required: true,
		},
	},

	computed: {
		text() {
			return this.clickableLinks(escapeHtml(this.data.text)).replace(/\n/g, '<br>')
		},
	},

	methods: {
		clickableLinks(message) {
			/**
			 * In Talk we only parse URLs with a protocol to avoid undesired
			 * clickables like composer.json. Therefor the method and regex were
			 * copied from OCP.Comments and adjusted accordingly.
			 */
			// var urlRegex = /(\s|^)(https?:\/\/)?((?:[-A-Z0-9+_]+\.)+[-A-Z]+(?:\/[-A-Z0-9+&@#%?=~_|!:,.;()]*)*)(\s|$)/ig;
			const urlRegex = /(\s|\(|^)(https?:\/\/)((?:[-A-Z0-9+_]+\.)+[-A-Z]+(?:\/[-A-Z0-9+&@#%?=~_|!:,.;()]*)*)(?=\s|\)|$)/ig
			return message.replace(urlRegex, function(_, leadingSpace, protocol, url) {
				let trailingClosingBracket = ''
				if (url.substr(-1) === ')' && (url.indexOf('(') === -1 || leadingSpace === '(')) {
					url = url.substr(0, url.length - 1)
					trailingClosingBracket = ')'
				}
				const link = protocol + url

				return leadingSpace + '<a class="external" target="_blank" rel="noopener noreferrer" href="' + link + '">' + link + '</a>' + trailingClosingBracket
			})
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .external:after {
	content: " ↗";
}
</style>
