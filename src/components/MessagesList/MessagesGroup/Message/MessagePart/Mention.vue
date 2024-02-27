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
	<span ref="mention" class="mention">
		<NcUserBubble v-if="size"
			:display-name="name"
			:avatar-image="avatarUrl"
			:user="id"
			:size="size"
			:primary="primary" />
	</span>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcUserBubble from '@nextcloud/vue/dist/Components/NcUserBubble.js'

import { isDarkTheme } from '../../../../../utils/isDarkTheme.js'

export default {
	name: 'Mention',

	components: {
		NcUserBubble,
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
		server: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			size: null,
		}
	},

	computed: {
		isMentionToAll() {
			return this.type === 'call'
		},
		isGroupMention() {
			return this.type === 'user-group' || this.type === 'group'
		},
		isMentionToGuest() {
			return this.type === 'guest'
		},
		isRemoteUser() {
			return this.type === 'user' && this.server !== ''
		},
		isCurrentGuest() {
			// On mention bubbles the id is actually "guest/ACTOR_ID" for guests
			// This is to make sure guests can never collide with users,
			// while storing them as "… @id …" in chat messages.
			// So when comparing a guest we have to prefix "guest/"
			// when comparing the id
			return this.$store.getters.isActorGuest()
				&& this.id === ('guest/' + this.$store.getters.getActorId())
		},
		isCurrentUser() {
			if (this.isRemoteUser) {
				// For now, we don't highlight remote users even if they are the one
				return false
			}

			return this.$store.getters.isActorUser()
				&& this.id === this.$store.getters.getUserId()
		},
		isCurrentUserGroup() {
			return this.isGroupMention
				&& loadState('spreed', 'user_group_ids', []).includes(this.id)
		},
		primary() {
			return this.isMentionToAll || this.isCurrentUser
				|| (this.isGroupMention && this.isCurrentUserGroup)
				|| (this.isMentionToGuest && this.isCurrentGuest)
		},
		avatarUrl() {
			if (this.isGroupMention) {
				return 'icon-group-forced-white'
			} else if (this.isMentionToGuest || this.isRemoteUser) {
				return 'icon-user-forced-white'
			} else if (!this.isMentionToAll) {
				return undefined
			}

			return generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar' + (isDarkTheme ? '/dark' : ''), {
				token: this.id,
			})
		},
	},

	mounted() {
		this.size = parseInt(window.getComputedStyle(this.$refs.mention).fontSize, 10) * 4 / 3 ?? 20
	}
}
</script>

<style lang="scss" scoped>
.mention {
	display: contents;
	white-space: nowrap;
}
</style>
