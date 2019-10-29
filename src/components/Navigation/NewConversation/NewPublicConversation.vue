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
	<AppContentListItem
		:title="searchText"
		@click="createPublicConversation">
		<ConversationIcon
			slot="icon"
			:item="iconData" />
		<template slot="subtitle">
			{{ t('spreed', 'Public conversation') }}
		</template>
	</AppContentListItem>
</template>

<script>
import ConversationIcon from '../../ConversationIcon'
import AppContentListItem from '../ConversationsList/AppContentListItem/AppContentListItem'
import { EventBus } from '../../../services/EventBus'
import { createPublicConversation } from '../../../services/conversationsService'
import { CONVERSATION } from '../../../constants'

export default {
	name: 'NewPublicConversation',
	components: {
		AppContentListItem,
		ConversationIcon,
	},
	props: {
		searchText: {
			type: String,
			default: '',
		},
	},
	computed: {
		iconData() {
			return {
				type: CONVERSATION.TYPE.PUBLIC,
			}
		},
	},
	methods: {
		async createPublicConversation() {
			const response = await createPublicConversation(this.searchText)
			const conversation = response.data.ocs.data
			this.$store.dispatch('addConversation', conversation)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			console.debug(response)
			EventBus.$emit('resetSearchFilter')
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
