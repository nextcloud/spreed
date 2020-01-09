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
	<ul class="contacts-list">
		<AppContentListItem
			v-for="item of circles"
			:key="item.id"
			:title="item.label"
			@click="onClick(item.id)">
			<template v-slot:icon>
				<ConversationIcon
					:item="iconData" />
			</template>
		</AppContentListItem>
	</ul>
</template>

<script>
import ConversationIcon from '../../ConversationIcon'
import AppContentListItem from '../ConversationsList/AppContentListItem/AppContentListItem'
import { CONVERSATION } from '../../../constants'

export default {
	name: 'CirclesList',
	components: {
		ConversationIcon,
		AppContentListItem,
	},
	props: {
		circles: {
			type: Array,
			required: true,
		},
		isLoading: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		iconData() {
			return {
				type: CONVERSATION.TYPE.GROUP,
			}
		},
	},
	methods: {
		// forward click event
		onClick(circleId) {
			this.$emit('click', circleId)
		},
	},
}
</script>

<style lang="scss" scoped>
.ellipsis {
	text-overflow: ellipsis;
}
.contacts-list {
	overflow: visible;
	display: block;
}

</style>
