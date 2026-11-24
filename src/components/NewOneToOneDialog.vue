<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { AutocompleteResult } from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NewConversationContactsPage from './NewConversationDialog/NewConversationContactsPage.vue'

const emit = defineEmits<{
	(event: 'close'): void
}>()

const NO_SELECTION: AutocompleteResult[] = []

const store = useStore()
const router = useRouter()

const isCreating = ref(false)

/**
 * Open the one-to-one conversation with the clicked user, creating it when needed
 *
 * @param selection - the contacts page selection, the clicked user is the last item
 */
async function startConversation(selection: AutocompleteResult[]) {
	const user = selection.at(-1)
	if (!user || isCreating.value) {
		return
	}

	isCreating.value = true
	const conversation = await store.dispatch('createOneToOneConversation', user.id)
	isCreating.value = false

	if (!conversation) {
		showError(t('spreed', 'Error while creating the conversation'))
		return
	}

	emit('close')
	await router.push({ name: 'conversation', params: { token: conversation.token } })
}
</script>

<template>
	<NcDialog
		:name="t('spreed', 'New private conversation')"
		size="normal"
		@update:open="emit('close')">
		<NewConversationContactsPage
			class="new-one-to-one"
			:selectedParticipants="NO_SELECTION"
			onlyUsers
			onlyLocal
			@update:selectedParticipants="startConversation" />
	</NcDialog>
</template>

<style lang="scss" scoped>
.new-one-to-one {
	display: flex;
	flex-direction: column;
	// A fixed height keeps the search field in place while the results change
	height: 50vh;
	padding-bottom: calc(var(--default-grid-baseline) * 2);
}
</style>
