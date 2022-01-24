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
			<input id="request-password-button"
				class="primary button-vue"
				type="button"
				:value="t('spreed', 'Request password')"
				:disabled="isRequestInProgress"
				@click="requestPassword">
			<div class="icon" :class="iconClass" />
		</div>
		<p v-if="hasRequestFailed" class="warning error-message">
			{{ t('spreed', 'Error requesting the password.') }}
		</p>
	</div>
</template>

<script>
import { getPublicShareAuthConversationToken } from './services/publicShareAuthService'
import browserCheck from './mixins/browserCheck'
import '@nextcloud/dialogs/styles/toast.scss'

export default {

	name: 'PublicShareAuthRequestPasswordButton',

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

<style lang="scss" scoped>
/* Request password button has the appearance of the log in button */
.request-password-wrapper {
	position: relative;
	width: 280px;
	margin: 16px auto;
}

.request-password-wrapper .icon {
	position: absolute;
	right: 23px;
	pointer-events: none;
}

input#request-password-button {
	width: 269px;
	padding: 10px 10px;
}

input#request-password-button:disabled ~ .icon {
	opacity: 0.5;
}
</style>
