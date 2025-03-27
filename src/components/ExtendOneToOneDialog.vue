<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import debounce from 'debounce'
import { ref } from 'vue'
import { useRouter } from 'vue-router/composables'

import IconAccountMultiplePlus from 'vue-material-design-icons/AccountMultiplePlus.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import ParticipantsSearchResults from './RightSidebar/Participants/ParticipantsSearchResults.vue'
import SearchBox from './UIShared/SearchBox.vue'

import { useStore } from '../composables/useStore.js'
import { autocompleteQuery } from '../services/coreService.ts'
import type { AutocompleteResult } from '../types/index.ts'

const props = defineProps<{
	token: string,
	container?: string,
}>()

const store = useStore()
const router = useRouter()

const loading = ref(false)
const isFocused = ref(false)
const searchText = ref('')
const searchResults = ref<AutocompleteResult[]>([])

const debounceFetchSearchResults = debounce(fetchSearchResults, 250)

function handleInput() {
	loading.value = true
	debounceFetchSearchResults()
}

function abortSearch() {
	loading.value = false
	searchText.value = ''
	searchResults.value = []
}

/**
 * Fetch autocomplete user results from the server
 */
async function fetchSearchResults() {
	if (searchText.value === '') {
		abortSearch()
		return
	}

	try {
		const response = await autocompleteQuery({
			searchText: searchText.value,
			token: props.token,
			onlyUsers: true,
		}, {})

		searchResults.value = response?.data?.ocs?.data || []
		loading.value = false
	} catch (exception) {
		console.error(exception)
		showError(t('spreed', 'An error occurred while performing the search'))
	}
}

/**
 * Add current participants and selected user to the conversation
 *
 * @param {object} participant The autocomplete suggestion to start a conversation with
 * @param {string} participant.id The ID of the target
 * @param {string} participant.source The source of the target
 */
async function extendOneToOneConversation(participant: AutocompleteResult) {
	const newConversation = await store.dispatch('extendOneToOneConversation', {
		token: props.token,
		participant,
	})
	if (newConversation) {
		await router.push({ name: 'conversation', params: { token: newConversation.token } })
	}
}
</script>

<template>
	<NcPopover :container="container"
		popup-role="dialog">
		<template #trigger>
			<NcButton class="start-group__button"
				type="tertiary"
				:title="t('spreed', 'Start a group conversation')"
				:aria-label="t('spreed', 'Start a group conversation')">
				<template #icon>
					<IconAccountMultiplePlus :size="20" />
				</template>
			</NcButton>
		</template>
		<template #default>
			<div class="start-group__content">
				<SearchBox class="start-group__input"
					:value.sync="searchText"
					:is-focused.sync="isFocused"
					:placeholder-text="t('spreed', 'Search or add participants')"
					@input="handleInput"
					@abort-search="abortSearch" />

				<ParticipantsSearchResults class="search-results"
					:search-results="searchResults"
					:contacts-loading="loading"
					:no-results="searchResults.length === 0"
					:search-text="searchText"
					add-single-user
					@click="extendOneToOneConversation" />
			</div>
		</template>
	</NcPopover>
</template>

<style lang="scss" scoped>
.start-group {
	&__content {
		display: flex;
		flex-direction: column;
		width: 300px;
		padding: var(--default-grid-baseline);
	}

	&__empty-content {
		:deep(.empty-content__description) {
			text-align: center;
		}
	}
}
</style>
