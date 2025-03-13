<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal :can-close="false"
		:close-on-click-outside="false"
		:label-id="dialogHeaderId"
		size="small">
		<div class="modal__content">
			<div class="conversation-information">
				<ConversationIcon :item="conversation" hide-user-status />
				<h2 :id="dialogHeaderId" class="nc-dialog-alike-header">
					{{ conversationDisplayName }}
				</h2>
			</div>
			<p class="description">
				{{ conversationDescription }}
			</p>

			<label for="textField">{{ t('spreed', 'Enter your name') }}</label>
			<NcTextField id="textField"
				v-model="guestUserName"
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

			<div class="separator" />

			<div class="login-info">
				<span> {{ t('spreed', 'Do you already have an account?') }}</span>
				<NcButton type="secondary"
					:href="getLoginUrl()">
					{{ t('spreed', 'Log in') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { ref } from 'vue'

import Check from 'vue-material-design-icons/CheckBold.vue'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import ConversationIcon from './ConversationIcon.vue'

import { useId } from '../composables/useId.ts'
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
		const guestUserName = ref('')
		const dialogHeaderId = `guest-welcome-header-${useId()}`

		return {
			guestNameStore,
			guestUserName,
			dialogHeaderId,
		}
	},

	computed: {
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

		getLoginUrl() {
			const currentUrl = window.location.pathname
			const loginBaseUrl = generateUrl('/login')
			const redirectUrl = encodeURIComponent(currentUrl)
			return `${loginBaseUrl}?redirect_url=${redirectUrl}`
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding: calc(var(--default-grid-baseline) * 3);
	background-color: var(--color-main-background);
}

.conversation-information {
	margin-top: 5px;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.description {
	margin-bottom: 12px;
	max-height: 8lh;
	overflow-x: hidden;
	overflow-y: auto;
	text-overflow: ellipsis;
}

.username-form__input {
	margin-bottom: 20px;
}

.submit-button {
	margin: 0 auto;
}

.login-info {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding-top: calc(var(--default-grid-baseline) * 2);
}

.separator {
    margin: calc(var(--default-grid-baseline) * 5) 0 var(--default-grid-baseline);
    border-top: 1px solid;
}
</style>
