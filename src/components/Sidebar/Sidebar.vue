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
	<AppSidebar
		v-if="opened"
		:title="conversation.displayName"
		:starred.sync="conversation.isFavorite"
		@close="show=false" />
</template>

<script>
import AppSidebar from 'nextcloud-vue/dist/Components/AppSidebar'
import { EventBus } from '../../services/EventBus'

export default {
	name: 'Sidebar',
	components: {
		AppSidebar,
	},

	data() {
		return {
			show: true
		}
	},
	beforeMount() {
		EventBus.$on('routeChange', () => {
			this.show = true
		})
	},
	computed: {
		opened() {
			return !!this.token && this.show
		},
		token() {
			return this.$route.params.token
		},
		conversation() {
			if (this.$store.getters.conversations[this.token]) {
				return this.$store.getters.conversations[this.token]
			}
			return {
				displayName: '',
				isFavorite: false,
			}
		},
	},
}
</script>
