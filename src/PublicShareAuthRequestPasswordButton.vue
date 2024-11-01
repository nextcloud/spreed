<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Fragment>
		<!-- "submit-wrapper" is used to mimic the login button and thus get
			automatic colouring of the confirm icon by the Theming app. -->
		<div id="submit-wrapper" class="request-password-wrapper">
			<NcButton id="request-password-button"
				type="primary"
				:wide="true"
				:disabled="isRequestInProgress"
				@click="requestPassword">
				{{ t('spreed', 'Request password') }}
			</NcButton>
		</div>
		<p v-if="hasRequestFailed" class="warning error-message">
			{{ t('spreed', 'Error requesting the password.') }}
		</p>
	</Fragment>
</template>

<script>
import { Fragment } from 'vue-frag'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { getPublicShareAuthConversationToken } from './services/publicShareAuthService.js'
import { checkBrowser } from './utils/browserCheck.ts'

export default {

	name: 'PublicShareAuthRequestPasswordButton',

	components: {
		NcButton,
		Fragment,
	},

	props: {
		shareToken: {
			type: String,
			required: true,
		},
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

		token() {
			return this.$store.getters.getToken()
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
				const token = await getPublicShareAuthConversationToken(this.shareToken)

				this.$store.dispatch('updateToken', token)
			} catch (exception) {
				this.hasRequestFailed = true
			}

			this.isRequestLoading = false
		},
	},
}
</script>
