<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcButton type="success" @click="disableLobby">
		<template #icon>
			<LockOpen />
		</template>
		{{ t('spreed', 'Disable lobby' ) }}
	</NcButton>
</template>

<script>
import LockOpen from 'vue-material-design-icons/LockOpen.vue'

// eslint-disable-next-line
// import { showError, showSuccess } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'LobbyStatus',
	components: {
		NcButton,
		LockOpen,
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
				window.OCP.Toast.success(t('spreed', 'You opened the conversation to everyone'))
			} catch (e) {
				console.error('Error occurred when opening the conversation to everyone', e)
				window.OCP.Toast.error(t('spreed', 'Error occurred when opening the conversation to everyone'))
			}
			this.isLobbyStateLoading = false
		},
	},
}

</script>
