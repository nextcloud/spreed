<!--
  - @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  -
  - @license AGPL-3.0-or-later
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
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Message expiration') }}
		</h4>
		<div class="app-settings-section__hint">
			{{ t('spreed', 'Chat messages can be expired after a certain time. Note: Files shared in chat will not be deleted for the owner, but will no longer be shared in the conversation.') }}
		</div>

		<template v-if="canModerate">
			<NcSelect id="moderation_settings_message_expiration"
				:input-label="t('spreed', 'Set message expiration')"
				:value="selectedOption"
				:options="expirationOptions"
				label="label"
				close-on-select
				:clearable="false"
				@option:selected="changeExpiration" />
		</template>

		<template v-else>
			<h5 class="app-settings-section__subtitle">
				{{ t('spreed', 'Current message expiration') }}
			</h5>
			<p>{{ selectedOption.label }}</p>
		</template>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'

import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

export default {
	name: 'ExpirationSettings',

	components: {
		NcSelect,
	},

	props: {
		token: {
			type: String,
			default: null,
		},

		canModerate: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			overwriteExpiration: undefined,
			defaultExpirationOptions: [
				{ id: 3600, label: n('spreed', '%n hour', '%n hours', 1) },
				{ id: 28800, label: n('spreed', '%n hour', '%n hours', 8) },
				{ id: 86400, label: n('spreed', '%n day', '%n days', 1) },
				{ id: 604800, label: n('spreed', '%n week', '%n weeks', 1) },
				{ id: 2419200, label: n('spreed', '%n week', '%n weeks', 4) },
				{ id: 0, label: t('spreed', 'Off') },
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

			return this.expirationOptions.at(-1)
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
				showError(t('spreed', 'Error when trying to set message expiration'))
				console.error(error)
			}

			this.overwriteExpiration = undefined
		},
	},
}
</script>

<style lang="scss" scoped>
</style>
