<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
import { provide, ref } from 'vue'

import RoomSelector from '../../RoomSelector.vue'

export default {

	name: 'OpenConversationsList',

	components: {
		RoomSelector,
	},

	setup() {
		provide('exposeDescription', ref(true))
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
