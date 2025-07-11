<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="submit-wrapper" class="request-password-wrapper">
		<!-- "submit-wrapper" is used to mimic the login button and thus get
		automatic colouring of the confirm icon by the Theming app. -->
		<NcButton id="request-password-button"
			variant="primary"
			:wide="true"
			:disabled="isRequestInProgress"
			@click="requestPassword">
			{{ t('spreed', 'Request password') }}
		</NcButton>
	</div>
	<p v-if="hasRequestFailed" class="warning error-message">
		{{ t('spreed', 'Error requesting the password.') }}
	</p>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useGetToken } from './composables/useGetToken.ts'
import { getPublicShareAuthConversationToken } from './services/filesIntegrationServices.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'

export default {

	name: 'PublicShareAuthRequestPasswordButton',

	components: {
		NcButton,
	},

	props: {
		shareToken: {
			type: String,
			required: true,
		},
	},

	setup() {
		return {
			token: useGetToken(),
			tokenStore: useTokenStore(),
		}
	},

	data() {
		return {
			isRequestLoading: false,
			hasRequestFailed: false,
		}
	},

	computed: {
		iconClass() {
			return {
				'icon-confirm-white': !this.isRequestInProgress,
				'icon-loading-small-dark': this.isRequestInProgress,
			}
		},

		isRequestInProgress() {
			return this.isRequestLoading || !!this.token
		},
	},

	methods: {
		t,
		async requestPassword() {
			checkBrowser()

			this.hasRequestFailed = false
			this.isRequestLoading = true

			try {
				const response = await getPublicShareAuthConversationToken(this.shareToken)

				this.tokenStore.updateToken(response.data.ocs.data.token)
			} catch (exception) {
				this.hasRequestFailed = true
			}

			this.isRequestLoading = false
		},
	},
}
</script>
