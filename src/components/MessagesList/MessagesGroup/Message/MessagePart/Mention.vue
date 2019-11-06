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
	<UserBubble
		:display-name="data.name"
		:user="data.id"
		:primary="isCurrentUser" />
</template>

<script>

import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'Mention',

	components: {
		UserBubble,
	},

	props: {
		data: {
			type: Object,
			default() {
				return {
					name: '',
					link: '',
				}
			},
		},
	},

	computed: {
		isCurrentUser() {
			return this.data.id === getCurrentUser().uid
		},
	},
}
</script>
