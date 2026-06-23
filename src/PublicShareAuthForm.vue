<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->

<script setup lang="ts">
import { getRequestToken } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcGuestContent from '@nextcloud/vue/components/NcGuestContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

const publicShareAuth = loadState<{
	showBruteForceWarning?: boolean | null
	wrongpw?: boolean | null
}>('spreed', 'talk-public-share-auth', {})

const requestToken = getRequestToken()

const password = ref('')
</script>

<template>
	<NcGuestContent class="public-share__content">
		<h2>{{ t('spreed', 'This conversation is password-protected.') }}</h2>
		<NcNoteCard v-if="publicShareAuth.showBruteForceWarning" type="warning">
			{{ t('spreed', 'We have detected multiple invalid password attempts from your IP. Therefore your next attempt is throttled up to 30 seconds.') }}
		</NcNoteCard>
		<NcNoteCard v-if="publicShareAuth.wrongpw" type="error">
			{{ t('spreed', 'The password is wrong. Try again.') }}
		</NcNoteCard>
		<form
			class="public-share__form"
			method="POST">
			<NcPasswordField
				v-model="password"
				:label="t('spreed', 'Password')"
				autofocus
				autocomplete="new-password"
				autocapitalize="off"
				spellcheck="false"
				name="password" />

			<input type="hidden" name="requesttoken" :value="requestToken">

			<NcButton type="submit" variant="primary" wide>
				{{ t('spreed', 'Submit') }}
			</NcButton>
		</form>
	</NcGuestContent>
</template>

<style scoped lang="scss">
.public-share__content {
	box-sizing: border-box;
	width: fit-content;
}

.public-share__form {
	display: flex;
	flex-direction: column;
	gap: calc(2 * var(--default-grid-baseline));
}
</style>
