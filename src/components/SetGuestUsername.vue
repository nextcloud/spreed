<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="username-form">
		<!-- eslint-disable-next-line vue/no-v-html -->
		<h3 v-html="displayNameLabel" />

		<NcButton v-if="!isEditingUsername"
			@click="handleEditUsername">
			{{ t('spreed', 'Edit display name') }}
			<template #icon>
				<Pencil :size="20" />
			</template>
		</NcButton>

		<NcTextField v-else
			ref="usernameInput"
			:value.sync="guestUserName"
			:placeholder="t('spreed', 'Guest')"
			class="username-form__input"
			:show-trailing-button="!!guestUserName"
			trailing-button-icon="arrowRight"
			:trailing-button-label="t('spreed', 'Save name')"
			@trailing-button-click="handleChooseUserName"
			@keydown.enter="handleChooseUserName"
			@keydown.esc="handleEditUsername" />
	</div>
</template>

<script>
import escapeHtml from 'escape-html'

import Pencil from 'vue-material-design-icons/Pencil.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { useGuestNameStore } from '../stores/guestName.js'

export default {
	name: 'SetGuestUsername',

	components: {
		NcButton,
		NcTextField,
		Pencil,
	},

	setup() {
		const guestNameStore = useGuestNameStore()
		return { guestNameStore }
	},

	data() {
		return {
			guestUserName: '',
			isEditingUsername: false,
			delayHandleUserNameFromBrowserStorage: false,
		}
	},

	computed: {
		actorDisplayName() {
			return this.$store.getters.getDisplayName() || t('spreed', 'Guest')
		},
		displayNameLabel() {
			return t('spreed', 'Display name: {name}', {
				name: `<strong>${escapeHtml(this.actorDisplayName)}</strong>`,
			}, undefined, { escape: false })
		},
		actorId() {
			return this.$store.getters.getActorId()
		},
		token() {
			return this.$store.getters.getToken()
		},
	},

	watch: {
		actorId() {
			if (this.delayHandleUserNameFromBrowserStorage) {
				console.debug('Saving guest name from browser storage to the session')
				this.handleChooseUserName()
				this.delayHandleUserNameFromBrowserStorage = false
			}
		},
		// Update the input field text
		actorDisplayName(newName) {
			this.guestUserName = newName
		},

	},

	mounted() {
		// FIXME use @nextcloud/browser-storage or OCP when decided
		// https://github.com/nextcloud/nextcloud-browser-storage/issues/3
		this.guestUserName = localStorage.getItem('nick') || ''
		if (this.guestUserName && this.actorDisplayName !== this.guestUserName) {
			// Browser storage has a name, so we use that.
			if (this.actorId) {
				console.debug('Saving guest name from browser storage to the session')
				this.handleChooseUserName()
			} else {
				console.debug('Delay saving guest name from browser storage to the session')
				this.delayHandleUserNameFromBrowserStorage = true
			}
		}
	},

	methods: {
		handleChooseUserName() {
			this.guestNameStore.submitGuestUsername(this.token, this.guestUserName)
			this.isEditingUsername = false
		},

		handleEditUsername() {
			this.isEditingUsername = !this.isEditingUsername
			if (this.isEditingUsername) {
				this.$nextTick(() => {
					this.$refs.usernameInput.focus()
				})
			}
		},
	},

}
</script>

<style lang="scss" scoped>
.username-form {
	padding: 0 12px;
	margin: 0 auto 12px;

	& &__input {
		width: 300px;
		height: var(--default-clickable-area);
	}
}

</style>
