<!--
  - @copyright Copyright (c) 2020 Daniel Calviño Sánchez <danxuliu@gmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div>
		<!-- "submit-wrapper" is used to mimic the login button and thus get
			automatic colouring of the confirm icon by the Theming app. -->
		<div id="submit-wrapper" class="request-password-wrapper">
			<ButtonVue id="request-password-button"
				type="primary"
				:wide="true"
				:disabled="isRequestInProgress"
				@click="requestPassword"
				@keydown.enter="requestPassword">
				{{ t('spreed', 'Request password') }}
			</ButtonVue>
		</div>
		<p v-if="hasRequestFailed" class="warning error-message">
			{{ t('spreed', 'Error requesting the password.') }}
		</p>
	</div>
</template>

<script>
import ButtonVue from '@nextcloud/vue/dist/Components/ButtonVue'
import { getPublicShareAuthConversationToken } from './services/publicShareAuthService.js'
import browserCheck from './mixins/browserCheck.js'
import '@nextcloud/dialogs/styles/toast.scss'

export default {

	name: 'PublicShareAuthRequestPasswordButton',

	components: {
		ButtonVue,
	},

	mixins: [
		browserCheck,
	],

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
		async requestPassword() {
			// see browserCheck mixin
			this.checkBrowser()

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
