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
	<form
		class="username-form"
		@submit.prevent="handleChooseUserName">
		<h3>
			{{ t('spreed', 'Display name: ') }} <strong>{{ actorDisplayName ? actorDisplayName : t('spreed', 'Guest') }}</strong>
			<button
				class="icon-rename"
				@click.prevent="handleEditUsername">
				{{ t('spreed', 'Edit') }}
			</button>
		</h3>
		<div
			v-if="isEditingUsername"
			class="username-form__wrapper">
			<input
				ref="usernameInput"
				v-model="guestUserName"
				:placeholder="t('spreed', 'Guest')"
				class="username-form__input"
				type="text"
				@keydown.enter="handleChooseUserName"
				@keydown.esc="isEditingUsername = !isEditingUsername">
			<button
				class="username-form__button"
				type="submit">
				<div class="icon-confirm" />
			</button>
		</div>
	</form>
</template>

<script>
import { setGuestUserName } from '../services/participantsService'

export default {
	name: 'SetGuestUsername',

	data() {
		return {
			guestUserName: '',
			isEditingUsername: false,
		}
	},

	computed: {
		actorDisplayName() {
			return this.$store.getters.getDisplayName()
		},
		token() {
			return this.$store.getters.getToken()
		},
	},

	methods: {
		async handleChooseUserName() {
			const previousName = this.$store.getters.getDisplayName()
			try {
				this.$store.dispatch('setDisplayName', this.guestUserName)
				this.$store.dispatch('forceGuestName', {
					token: this.token,
					actorId: this.$store.getters.getActorId().substring(6),
					actorDisplayName: this.guestUserName,
				})
				await setGuestUserName(this.token, this.guestUserName)
				this.isEditingUsername = false
			} catch (exception) {
				this.$store.dispatch('setDisplayName', previousName)
				this.$store.dispatch('forceGuestName', {
					token: this.token,
					actorId: this.$store.getters.getActorId().substring(6),
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
	& .icon-rename {
		margin-left: 8px;
		padding-left: 36px;
		background-position: 12px;
	}
	&__wrapper {
		display: flex;
	}
	&__input {
		padding-right: var(--clickable-area);
		width: 300px;
	}
	&__button {
		margin-left: -44px;
		background-color: transparent;
		border: none;
	}
}

</style>
