<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="username-form">
		<!-- eslint-disable-next-line vue/no-v-html -->
		<h3 v-html="displayNameLabel" />

		<NcButton v-if="!isEditingUsername"
			@click="toggleEdit">
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
			trailing-button-icon="arrowEnd"
			:trailing-button-label="t('spreed', 'Save name')"
			@trailing-button-click="updateDisplayName"
			@keydown.enter="updateDisplayName"
			@keydown.esc="toggleEdit" />

		<div class="login-info">
			<span> {{ t('spreed', 'Do you already have an account?') }}</span>
			<NcButton
				class="login-info__button"
				variant="secondary"
				:href="loginUrl">
				{{ t('spreed', 'Log in') }}
			</NcButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import type { NextcloudUser } from '@nextcloud/auth'

import { getGuestNickname } from '@nextcloud/auth'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import escapeHtml from 'escape-html'
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { EventBus } from '../services/EventBus.ts'
import { useActorStore } from '../stores/actor.ts'
import { useGuestNameStore } from '../stores/guestName.js'

const loginUrl = `${generateUrl('/login')}?redirect_url=${encodeURIComponent(window.location.pathname)}`

const actorStore = useActorStore()
const guestNameStore = useGuestNameStore()
const token = useGetToken()

const usernameInput = useTemplateRef('usernameInput')

const guestUserName = ref(getGuestNickname() || '')
const isEditingUsername = ref(false)

const actorDisplayName = computed<string>(() => actorStore.displayName || guestUserName.value || t('spreed', 'Guest'))
const displayNameLabel = computed(() => t('spreed', 'Display name: {name}', {
	name: `<strong>${escapeHtml(actorDisplayName.value)}</strong>`,
}, { escape: false }))

watch(actorDisplayName, (newValue) => {
	guestUserName.value = newValue
})

/** Initially set displayName in store, if available from BrowserStorage */
let delayUpdateDisplayName = false
if (guestUserName.value && !actorStore.displayName) {
	actorStore.setDisplayName(guestUserName.value)
	delayUpdateDisplayName = true
}

/** Update guest displayName for other attendees */
EventBus.once('joined-conversation', () => {
	if (guestUserName.value && delayUpdateDisplayName) {
		console.debug('Saving guest name from browser storage to the session')
		updateDisplayName()
	}
})

/** Update guest displayName from public event (e.g. @nextcloud/auth) */
subscribe('user:info:changed', updateDisplayNameFromPublicEvent)
onBeforeUnmount(() => {
	unsubscribe('user:info:changed', updateDisplayNameFromPublicEvent)
})

/** Update guest username from public page user menu */
function updateDisplayNameFromPublicEvent(payload: NextcloudUser) {
	if (payload.displayName && payload.displayName !== guestUserName.value) {
		guestUserName.value = payload.displayName
		updateDisplayName()
	}
}

/** Set guest username locally and send request to server to update for other attendees */
function updateDisplayName() {
	guestNameStore.submitGuestUsername(token.value, guestUserName.value)
	isEditingUsername.value = false
}

/** Toggle editing state of username */
function toggleEdit() {
	isEditingUsername.value = !isEditingUsername.value
	if (isEditingUsername.value) {
		nextTick(() => {
			usernameInput.value!.focus()
		})
	}
}
</script>

<style lang="scss" scoped>
.username-form {
	margin-block-end: 12px;
	margin-inline: auto;
}

.login-info {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding-top: calc(var(--default-grid-baseline) * 2);

	&__button {
		flex-shrink: 0;
	}
}

</style>
