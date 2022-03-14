<!--
  - @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
  -
  - @author Vincent Petry <vincent@nextcloud.com>
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
	<Button type="primary" @click="disableLobby">
		{{ t('spreed', 'Disable lobby' ) }}
	</Button>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import Button from '@nextcloud/vue/dist/Components/Button'

export default {
	name: 'LobbyStatus',
	components: {
		Button,
	},
	props: {
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			isLobbyStateLoading: false,
		}
	},

	methods: {
		async disableLobby() {
			this.isLobbyStateLoading = true
			try {
				await this.$store.dispatch('toggleLobby', {
					token: this.token,
					enableLobby: false,
				})
				showSuccess(t('spreed', 'You opened the conversation to everyone'))
			} catch (e) {
				console.error('Error occurred when opening the conversation to everyone', e)
				showError(t('spreed', 'Error occurred when opening the conversation to everyone'))
			}
			this.isLobbyStateLoading = false
		},
	},
}

</script>
