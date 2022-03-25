<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<!-- Guest username setting form -->
	<form class="username-form"
		@submit.prevent="handleChooseUserName">
		<!-- eslint-disable-next-line vue/no-v-html -->
		<h3 v-html="displayNameLabel" />
		<Button @click.prevent="handleEditUsername">
			{{ t('spreed', 'Edit') }}
			<template #icon>
				<Pencil :size="20"
					title=""
					decorative />
			</template>
		</Button>
		<div v-if="isEditingUsername"
			class="username-form__wrapper">
			<input ref="usernameInput"
				v-model="guestUserName"
				:placeholder="t('spreed', 'Guest')"
				class="username-form__input"
				type="text"
				@keydown.enter="handleChooseUserName"
				@keydown.esc="isEditingUsername = !isEditingUsername">
			<Button class="username-form__button"
				native-type="submit"
				type="tertiary">
				<ArrowRight :size="20"
					title=""
					decorative />
			</Button>
		</div>
	</form>
</template>

<script>
import { setGuestUserName } from '../services/participantsService'
import Button from '@nextcloud/vue/dist/Components/Button'
import Pencil from 'vue-material-design-icons/Pencil'
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'

export default {
	name: 'SetGuestUsername',

	components: {
		Button,
		Pencil,
		ArrowRight,
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
			return this.$store.getters.getDisplayName()
		},
		displayNameLabel() {
			return t('spreed', 'Display name: <strong>{name}</strong>', {
				name: this.actorDisplayName ? this.actorDisplayName : t('spreed', 'Guest'),
			})
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
	},

	mounted() {
		// FIXME use @nextcloud/browser-storage or OCP when decided
		// https://github.com/nextcloud/nextcloud-browser-storage/issues/3
		this.guestUserName = localStorage.getItem('nick')
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
		async handleChooseUserName() {
			const previousName = this.$store.getters.getDisplayName()
			try {
				this.$store.dispatch('setDisplayName', this.guestUserName)
				this.$store.dispatch('forceGuestName', {
					token: this.token,
					actorId: this.$store.getters.getActorId(),
					actorDisplayName: this.guestUserName,
				})
				await setGuestUserName(this.token, this.guestUserName)
				if (this.guestUserName !== '') {
					localStorage.setItem('nick', this.guestUserName)
				} else {
					localStorage.removeItem('nick')
				}
				this.isEditingUsername = false
			} catch (exception) {
				this.$store.dispatch('setDisplayName', previousName)
				this.$store.dispatch('forceGuestName', {
					token: this.token,
					actorId: this.$store.getters.getActorId(),
					actorDisplayName: previousName,
				})
				console.debug(exception)
			}
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

/** Username form for guest users */
.username-form {
	padding: 0 12px;
	margin:auto;
	&__wrapper {
		display: flex;
		margin-top: 16px;
	}
	&__input {
		padding-right: var(--clickable-area);
		width: 230px;
	}
	&__button {
		margin-left: -52px;
	}
}

</style>
