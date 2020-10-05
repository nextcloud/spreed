<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <smarcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <smarcoambrosini@pm.me>
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
	<div id="talk-tab-mount" />
</template>

<script>
export default {
	name: 'FilesSidebarTab',
	data() {
		return {
			fileInfo: null,
			tab: null,
		}
	},
	mounted() {
		// Dirty hack to force the style on parent component
		const tabChat = document.querySelector('#tab-chat')
		tabChat.style.height = '100%'
		// Remove paddding to maximize space for the chat view
		tabChat.style.padding = '0'

		try {
			OCA.Talk.fileInfo = this.fileInfo
			this.tab = OCA.Talk.newTab()
			this.tab.$mount('#talk-tab-mount')
		} catch (error) {
			console.error('Unable to mount Chat tab', error)
		}
	},
	beforeDestroy() {
		try {
			OCA.Talk.fileInfo = null
			this.tab.$destroy()
		} catch (error) {
			console.error('Unable to unmount Chat tab', error)
		}
	},
	methods: {
		/**
		 * Update current fileInfo and fetch new data
		 * @param {Object} fileInfo the current file FileInfo
		 */
		async update(fileInfo) {
			this.fileInfo = fileInfo
			OCA.Talk.fileInfo = this.fileInfo
		},
	},
}
</script>
