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
	<Tab :icon="icon" :name="name" :class="{ 'icon-loading': loading }">
		{{t('spreed', 'Talk tab coming soon')}}
	</Tab>
</template>

<script>
import Tab from '@nextcloud/vue/dist/Components/AppSidebarTab'

import store from '../store'

export default {
	name: 'FilesSidebarTab',
	components: {
		Tab,
	},
	props: {
		// fileInfo will be given by the Sidebar
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
	},
	data() {
		return {
			icon: 'icon-talk',
			loading: false,
			name: t('spreed', 'Chat'),
			token: null,
		}
	},

	computed: {
		/**
		 * Needed to differenciate the tabs
		 * pulled from the AppSidebarTab component
		 *
		 * @returns {string}
		 */
		id() {
			return this.name.toLowerCase().replace(/ /g, '-')
		},

		/**
		 * Returns the current active tab
		 * needed because AppSidebarTab also uses $parent.activeTab
		 *
		 * @returns {string}
		 */
		activeTab() {
			return this.$parent.activeTab
		},
	},

	beforeMount() {

	},
	mounted() {
		console.info(this.fileInfo)
		this.$store = store
	},
}
</script>
