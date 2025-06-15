<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
			v-model="guestUserName"
			:placeholder="t('spreed', 'Guest')"
			class="username-form__input"
			:show-trailing-button="!!guestUserName"
			trailing-button-icon="arrowRight"
			:trailing-button-label="t('spreed', 'Save name')"
			@trailing-button-click="handleChooseUserName"
			@keydown.enter="handleChooseUserName"
			@keydown.esc="handleEditUsername" />

		<div class="login-info">
			<span> {{ t('spreed', 'Do you already have an account?') }}</span>
			<NcButton variant="secondary"
				:href="getLoginUrl()">
				{{ t('spreed', 'Log in') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { getGuestNickname } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import escapeHtml from 'escape-html'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { useActorStore } from '../stores/actor.ts'
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
		return {
			guestNameStore,
			actorStore: useActorStore(),
			token: useGetToken(),
		}
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
			return this.actorStore.displayName || t('spreed', 'Guest')
		},

		displayNameLabel() {
			return t('spreed', 'Display name: {name}', {
				name: `<strong>${escapeHtml(this.actorDisplayName)}</strong>`,
			}, undefined, { escape: false })
		},

		actorId() {
			return this.actorStore.actorId
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
		this.guestUserName = getGuestNickname() || ''
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
		t,
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

		getLoginUrl() {
			const currentUrl = window.location.pathname
			const loginBaseUrl = generateUrl('/login')
			const redirectUrl = encodeURIComponent(currentUrl)
			return `${loginBaseUrl}?redirect_url=${redirectUrl}`
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

.login-info {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding-top: calc(var(--default-grid-baseline) * 2);
}

</style>
