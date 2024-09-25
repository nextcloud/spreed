<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<RoomSelector v-if="modal"
		list-open-conversations
		show-postable-only
		:dialog-title="dialogTitle"
		@close="closeModal"
		@select="openConversation" />
</template>

<script>
import { provide, ref } from 'vue'

import { t } from '@nextcloud/l10n'

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
		dialogTitle() {
			return t('spreed', 'Join open conversations')
		},

	},

	expose: ['showModal'],

	methods: {
		t,
		showModal() {
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		openConversation(conversation) {
			this.$store.dispatch('addConversation', conversation)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } })
				.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			this.closeModal()
		},
	},

}

</script>
