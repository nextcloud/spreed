<!--
  - @copyright Copyright (c) 2023 Dorra Jaouad <dorra.jaoued1@gmail.com>
  -
  - @author Dorra Jaouad <dorra.jaoued1@gmail.com>
  - @license AGPL-3.0-or-later
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
	<RoomSelector v-if="modal"
		:container="container"
		list-open-conversations
		show-postable-only
		:dialog-title="dialogTitle"
		@close="closeModal"
		@select="openConversation" />
</template>

<script>

import RoomSelector from '../../RoomSelector.vue'

export default {

	name: 'OpenConversationsList',

	components: {
		RoomSelector,
	},

	data() {
		return {
			modal: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		dialogTitle() {
			return t('spreed', 'Join open conversations')
		},

	},

	expose: ['showModal'],

	methods: {
		showModal() {
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		openConversation({ token }) {
			this.$router.push({ name: 'conversation', params: { token } })
				.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			this.closeModal()
		},
	},

}

</script>
