<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:id="dialogMaskId"
		ref="dialog"
		:name="dialogTitle"
		closeOnClickOutside
		size="normal"
		@update:open="$emit('close')">
		<NewMessage
			ref="newMessage"
			role="region"
			class="send-message-dialog"
			:token="token"
			:container="modalContainerId"
			:aria-label="dialogTitle"
			dialog
			:broadcast="broadcast"
			@submit="handleSubmit" />
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { useId } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NewMessage from '../NewMessage/NewMessage.vue'

export default {
	name: 'SendMessageDialog',

	components: {
		NcDialog,
		NewMessage,
	},

	props: {
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * The dialog title
		 */
		dialogTitle: {
			type: String,
			default: '',
		},

		/**
		 * Broadcast messages to all breakout rooms of a given conversation. In
		 * case this is true, the token needs to be from a conversation that
		 * has breakout rooms configured.
		 */
		broadcast: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'submit'],

	setup() {
		const dialogMaskId = `new-message-upload-${useId()}`
		const modalContainerId = '#' + dialogMaskId

		return {
			dialogMaskId,
			modalContainerId,
		}
	},

	mounted() {
		this.$nextTick(() => {
			this.$refs.newMessage.focusInput()
		})
	},

	methods: {
		t,

		handleSubmit(event) {
			this.$emit('submit', event)
		},
	},

}
</script>

<style lang="scss" scoped>
.send-message-dialog {
	padding-bottom: calc(3 * var(--default-grid-baseline));
}
</style>
