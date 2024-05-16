<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Lock conversation') }}
		</h4>
		<NcNoteCard v-if="hasCall" type="warning">
			<p>
				{{ t('spreed', 'This will also terminate the ongoing call.') }}
			</p>
		</NcNoteCard>
		<div>
			<NcCheckboxRadioSwitch :model-value="isReadOnly"
				type="switch"
				aria-describedby="moderation_settings_lock_conversation_hint"
				:disabled="isReadOnlyStateLoading"
				@update:model-value="toggleReadOnly">
				{{ t('spreed', 'Lock the conversation to prevent anyone to post messages or start calls') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
// eslint-disable-next-line
// import { showError, showSuccess } from '@nextcloud/dialogs'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

import { CONVERSATION } from '../../constants.js'

export default {
	name: 'LockingSettings',

	components: {
		NcCheckboxRadioSwitch,
		NcNoteCard,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			isReadOnlyStateLoading: false,
		}
	},

	computed: {
		hasCall() {
			return this.conversation.hasCall
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isReadOnly() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
		},
	},

	methods: {
		async toggleReadOnly() {
			const newReadOnly = this.isReadOnly ? CONVERSATION.STATE.READ_WRITE : CONVERSATION.STATE.READ_ONLY
			this.isReadOnlyStateLoading = true
			try {
				await this.$store.dispatch('setReadOnlyState', {
					token: this.token,
					readOnly: newReadOnly,
				})
				if (newReadOnly) {
					window.OCP.Toast.success(t('spreed', 'You locked the conversation'))
				} else {
					window.OCP.Toast.success(t('spreed', 'You unlocked the conversation'))
				}
			} catch (e) {
				if (newReadOnly) {
					console.error('Error occurred when locking the conversation', e)
					window.OCP.Toast.error(t('spreed', 'Error occurred when locking the conversation'))
				} else {
					console.error('Error updating read-only state', e)
					window.OCP.Toast.error(t('spreed', 'Error occurred when unlocking the conversation'))
				}
			}
			this.isReadOnlyStateLoading = false
		},
	},

}
</script>
