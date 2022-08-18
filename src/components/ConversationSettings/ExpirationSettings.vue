<!--
  - @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div>
		<div class="app-settings-section__hint">
			{{ t('spreed', 'Expire chat messages after a certain time. Note files shared into the chat will only be unshared from the conversation but are not deleted for the owner.') }}
		</div>
		<NcMultiselect :value="selectedOption"
			:options="expirationOptions"
			:allow-empty="false"
			track-by="id"
			label="label"
			:close-on-select="true"
			@update:value="changeExpiration" />
	</div>
</template>

<script>
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect.js'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'ExpirationSettings',

	components: {
		NcMultiselect,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			overwriteExpiration: undefined,
			defaultExpirationOptions: [
				{ id: 0, label: t('spreed', 'Off') },
				{ id: 2419200, label: n('spreed', '%n week', '%n weeks', 4) },
				{ id: 604800, label: n('spreed', '%n week', '%n weeks', 1) },
				{ id: 86400, label: n('spreed', '%n day', '%n days', 1) },
				{ id: 28800, label: n('spreed', '%n hour', '%n hours', 8) },
				{ id: 3600, label: n('spreed', '%n hour', '%n hours', 1) },
			],
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		expirationOptions() {
			const expirationOptions = [...this.defaultExpirationOptions]

			const found = expirationOptions.find((option) => {
				return option.id === this.conversation.messageExpiration
			})
			if (!found) {
				expirationOptions.push({ id: this.conversation.messageExpiration, label: t('spreed', 'Custom expiration time') })
			}

			return expirationOptions
		},

		selectedOption() {
			if (this.overwriteExpiration) {
				return this.overwriteExpiration
			}

			const option = this.expirationOptions.find((option) => {
				return option.id === this.conversation.messageExpiration
			})
			if (option) {
				return option
			}

			return this.expirationOptions[this.expirationOptions.length - 1]
		},
	},

	methods: {
		async changeExpiration(expiration) {
			this.overwriteExpiration = expiration

			try {
				await this.$store.dispatch('setMessageExpiration', {
					token: this.token,
					seconds: expiration.id,
				})

				if (expiration.id === 0) {
					showSuccess(t('spreed', 'Message expiration disabled'))
				} else {
					showSuccess(t('spreed', 'Message expiration set: {duration}', {
						duration: expiration.label,
					}))
				}
			} catch (error) {
				showError(t('spreed', 'Error when trying to set the messages expiration'))
				console.error(error)
			}

			this.overwriteExpiration = undefined
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .mx-input {
	margin: 0;
}
</style>
