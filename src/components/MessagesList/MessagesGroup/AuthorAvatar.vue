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
	<NcAvatar v-if="isUser"
		:disable-tooltip="true"
		class="messages__avatar__icon"
		:user="authorId"
		:show-user-status="false"
		:disable-menu="disableMenu"
		:menu-container="menuContainer"
		menu-position="left"
		:display-name="displayName" />
	<div v-else-if="isDeletedUser"
		class="avatar-32px guest">
		X
	</div>
	<div v-else-if="isGuest"
		class="avatar-32px guest">
		{{ firstLetterOfGuestName }}
	</div>
	<div v-else-if="isChangelog"
		class="avatar-32px icon icon-changelog" />
	<div v-else
		class="avatar-32px bot">
		&gt;_
	</div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import { ATTENDEE } from '../../../constants.js'

export default {
	name: 'AuthorAvatar',
	components: {
		NcAvatar,
	},
	props: {
		authorType: {
			type: String,
			required: true,
		},
		authorId: {
			type: String,
			required: true,
		},
		displayName: {
			type: String,
			required: true,
		},
	},

	computed: {
		isChangelog() {
			return this.authorType === 'bots' && this.authorId === 'changelog'
		},
		isUser() {
			return this.authorType === 'users' || this.authorType === ATTENDEE.ACTOR_TYPE.BRIDGED
		},
		isDeletedUser() {
			return this.authorType === 'deleted_users'
		},
		isGuest() {
			return this.authorType === 'guests'
		},

		firstLetterOfGuestName() {
			const customName = this.displayName !== t('spreed', 'Guest') ? this.displayName : '?'
			return customName.charAt(0)
		},

		menuContainer() {
			return this.$store.getters.getMainContainerSelector()
		},

		disableMenu() {
			// disable the menu if accessing the conversation as guest
			// or the message sender is a bridged user
			return this.$store.getters.getActorType() === 'guests' || this.authorType === ATTENDEE.ACTOR_TYPE.BRIDGED
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../assets/avatar';

// size of avatars of chat message authors
$author-avatar-size: 32px;
@include avatar-mixin($author-avatar-size);

</style>
