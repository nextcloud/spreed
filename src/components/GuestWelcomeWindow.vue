<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal :container="container"
		:can-close="false"
		:close-on-click-outside="false"
		size="small">
		<div class="modal__content">
			<div class="conversation-information">
				<ConversationIcon :item="conversation" hide-user-status />
				<h2>{{ conversationDisplayName }}</h2>
			</div>
			<p class="description">
				{{ conversationDescription }}
			</p>

			<label for="textField">{{ t('spreed', 'Enter your name') }}</label>
			<NcTextField id="textField"
				:value.sync="guestUserName"
				:placeholder="t('spreed', 'Guest')"
				class="username-form__input"
				:show-trailing-button="false"
				label-outside
				@keydown.enter="handleChooseUserName" />

			<NcButton class="submit-button"
				type="primary"
				:disabled="invalidGuestUsername"
				@click="handleChooseUserName">
				{{ t('spreed', 'Submit name and join') }}
				<template #icon>
					<Check :size="20" />
				</template>
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import Check from 'vue-material-design-icons/CheckBold.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import ConversationIcon from './ConversationIcon.vue'

import { useGuestNameStore } from '../stores/guestName.js'

export default {
	name: 'GuestWelcomeWindow',

	components: {
		NcModal,
		NcTextField,
		ConversationIcon,
		NcButton,
		Check,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	setup() {
		const guestNameStore = useGuestNameStore()
		return { guestNameStore }
	},

	data() {
		return {
			guestUserName: '',
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		conversationDisplayName() {
			return this.conversation?.displayName
		},

		conversationDescription() {
			return this.conversation?.description
		},

		invalidGuestUsername() {
			return this.guestUserName.trim() === ''
		},
	},

	methods: {
		t,
		handleChooseUserName() {
			this.guestNameStore.submitGuestUsername(this.token, this.guestUserName)
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding: calc(var(--default-grid-baseline) * 4);
	background-color: var(--color-main-background);
	margin: 0px 12px;
}

.conversation-information {
	margin-top: 5px;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.description {
	margin-bottom: 12px;
}

.username-form__input {
	margin-bottom: 20px;
}

.submit-button {
	margin: 0 auto;
}
</style>
