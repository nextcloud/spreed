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
	<div class="mention">
		<UserBubble
			v-if="isMentionToAll"
			:display-name="name"
			:avatar-image="'icon-group-forced-white'"
			:primary="true" />
		<UserBubble
			v-else-if="isMentionToGuest"
			:display-name="name"
			:avatar-image="'icon-user-forced-white'"
			:primary="isCurrentGuest" />
		<UserBubble
			v-else
			:display-name="name"
			:user="id"
			:primary="isCurrentUser" />
	</div>
</template>

<script>

import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'

export default {
	name: 'Mention',

	components: {
		UserBubble,
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
	},

	computed: {
		isMentionToAll() {
			return this.type === 'call'
		},
		isMentionToGuest() {
			return this.type === 'guest'
		},
		isCurrentGuest() {
			// On mention bubbles the id is actually "guest/ACTOR_ID" for guests
			// This is to make sure guests can never collide with users,
			// while storing them as "… @id …" in chat messages.
			// So when comparing a guest we have to prefix "guest/"
			// when comparing the id
			return this.$store.getters.getActorType() === 'guests'
				&& this.id === ('guest/' + this.$store.getters.getActorId())
		},
		isCurrentUser() {
			return this.$store.getters.getActorType() === 'users'
				&& this.id === this.$store.getters.getUserId()
		},
	},
}
</script>

<style lang="scss" scoped>
.mention {
	display: contents;
	white-space: nowrap;
}
</style>
