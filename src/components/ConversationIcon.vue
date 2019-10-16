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
	<div v-if="itemClass"
		class="avatar icon" :class="itemClass" />
	<Avatar v-else
		:size="44"
		:user="item.displayName"
		:display-name="item.displayName" />
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import { Conversation } from '../constants'

export default {
	name: 'ConversationIcon',
	components: {
		Avatar
	},
	props: {
		item: {
			type: Object,
			default: function() {
				return {
					objectType: '',
					type: 0,
					displayName: ''
				}
			}
		}
	},
	computed: {
		itemClass() {
			if (this.item.objectType === 'file') {
				return 'icon-file'
			} else if (this.item.objectType === 'share:password') {
				return 'icon-password'
			} else if (this.item.type === Conversation.Type.CHANGELOG) {
				return 'icon-changelog'
			} else if (this.item.type === Conversation.Type.GROUP) {
				return 'icon-contacts'
			} else if (this.item.type === Conversation.Type.PUBLIC) {
				return 'icon-public'
			}

			return ''
		}
	}
}
</script>

<style lang="scss" scoped>

.icon {
	width: 44px;
	height: 44px;
	line-height: 44px;
	font-size: 24px;
	background-color: var(--color-background-darker);

	&.icon-changelog {
		background-size: 44px;
	}
	&.icon-public,
	&.icon-contacts,
	&.icon-password,
	&.icon-file,
	&.icon-mail {
		background-size: 20px;
	}

}

</style>
