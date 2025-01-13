<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import debounce from 'debounce'
import type { Emitter } from 'mitt'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { Route } from 'vue-router'
import { useRouter } from 'vue-router/composables'

import IconFilter from 'vue-material-design-icons/Filter.vue'
import IconMessageOutline from 'vue-material-design-icons/MessageOutline.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDateTime from '@nextcloud/vue/dist/Components/NcDateTime.js'
import NcDateTimePickerNative from '@nextcloud/vue/dist/Components/NcDateTimePickerNative.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import SearchBox from '../../UIShared/SearchBox.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'

import { useStore } from '../../../composables/useStore.js'
import { EventBus } from '../../../services/EventBus.ts'
import { searchMessages } from '../../../services/messagesService.ts'
import CancelableRequest from '../../../utils/cancelableRequest.js'

type UserFilterObject = {
	id: string
}

type MessageSearchResultAttributes = {
	conversation: string
	messageId: string
	actorType: string
	actorId: string
	timestamp: string
}

type MessageSearchResultEntry = {
	subline: string
	thumbnailUrl: string
	title: string
	resourceUrl: string
	icon: string
	rounded: boolean
	attributes: MessageSearchResultAttributes
}

type Events = {
  'route-change': { from: Route; to: Route }
}

type ExtendedEmitter = Emitter<Events>

const typedEventBus = EventBus as ExtendedEmitter

const searchBox = ref(null)
const isFocused = ref(false)
const searchResults = ref<MessageSearchResultEntry[]>([])
const searchText = ref('')
const fromUser = ref<UserFilterObject | null>(null)
const sinceDate = ref<Date | null>(null)
const untilDate = ref<Date | null>(null)
const searchLimit = ref(10)
const searchCursor = ref(0)
const searchDetailsOpened = ref(false)
const isFetchingResults = ref(false)
const isSearchExhausted = ref(false)

const router = useRouter()
const store = useStore()

const token = computed(() => store.getters.getToken())
const participantsInitialised = computed(() => store.getters.participantsInitialised(token.value))
const participants = computed<UserFilterObject>(() => {
	return store.getters.participantsList(token.value)
		.map(({ actorId, displayName, actorType }: { actorId: string; displayName: string; actorType: string}) => ({
			id: actorId,
			displayName,
			isNoUser: actorType !== 'users',
			user: actorId,
			disableMenu: true,
			showUserStatus: false,
		}))
})
const canLoadMore = computed(() => !isSearchExhausted.value && !isFetchingResults.value && searchCursor.value !== 0)

onMounted(() => {
	typedEventBus.on('route-change', onRouteChange)
})

onBeforeUnmount(() => {
	typedEventBus.off('route-change', onRouteChange)
	abortSearch()
})

const onRouteChange = ({ from, to }: { from: Route, to: Route }): void => {
	if (from.name === 'conversation'
		&& to.name === 'conversation'
		&& from.params.token === to.params.token) {
		return
	}
	if (to.name === 'conversation') {
		abortSearch()
	}
}

watch(searchText, (value) => {
	if (value.trim().length === 0) {
		searchResults.value = []
		searchCursor.value = 0
		isSearchExhausted.value = false
	}
})

/**
 * Cancel search and cleanup the search fields and results.
 */
function abortSearch() {
	cancelSearch.value('canceled')
	searchText.value = ''
	fromUser.value = null
	sinceDate.value = null
	untilDate.value = null
	searchDetailsOpened.value = false
	searchResults.value = []
	searchCursor.value = 0
}

/**
 * Continue fetching more search results
 */
function loadMore() {
	return fetchSearchResults(false)
}

/**
 *
 */
function fetchNewSearchResult() {
	return fetchSearchResults(true)
}

const cancelSearch = ref((cancel: string) => {})

/**
 * @param [isNew=true] Is it a new search (search parameters changed)?
 * Fetch the search results from the server
 */
async function fetchSearchResults(isNew = true) {
	// Don't search if the search text is empty
	if (searchText.value.trim().length === 0) {
		return
	}

	isFetchingResults.value = true

	try {
		cancelSearch.value('canceled')
		const { request, cancel } = CancelableRequest(searchMessages)
		cancelSearch.value = cancel

		if (isNew) {
			searchCursor.value = 0
		}
		if (searchCursor.value === 0) {
			searchResults.value = []
		}

		const term = searchText.value.trim()
		if (term.length === 0 && !fromUser.value && !sinceDate.value && !untilDate.value) {
			return
		}
		const response = await request({
			term: term.length !== 0 ? term : null,
			person: fromUser.value?.id,
			since: sinceDate.value?.toISOString(),
			until: untilDate.value?.toISOString(),
			limit: searchLimit.value,
			cursor: searchCursor.value || null,
			conversation: token.value,
		})

		const data = response?.data?.ocs?.data
		if (data?.entries.length > 0) {
			let entries = data?.entries

			isSearchExhausted.value = entries.length < searchLimit.value
			searchCursor.value = data.cursor

			// FIXME: remove the filter after the person filter is fixed on the server
			if (fromUser.value) {
				entries = entries.filter((entry) => entry.attributes.actorId === fromUser.value?.id)
				if (entries.length === 0 && !isSearchExhausted.value) {
					return await loadMore()
				}
			}

			searchResults.value = searchResults.value.concat(entries)
		}
	} catch (exception) {
		if (CancelableRequest.isCancel(exception)) {
			return
		}
		console.error('Error searching for messages', exception)
		showError(t('spreed', 'An error occurred while performing the search'))
	} finally {
		isFetchingResults.value = false
	}
}

/**
 * Search result on click handler
 * @param attributes Attributes from message search results
 */
function onClickMessageSearchResult(attributes: MessageSearchResultAttributes) {
	router.push({
		name: 'conversation',
		hash: `#message_${attributes.messageId}`,
		params: {
			token: attributes.conversation,
		},
	}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
}

const debounceFetchSearchResults = debounce(fetchNewSearchResult, 250)
</script>

<template>
	<div class="search-messages-tab">
		<div class="search-form">
			<div class="search-form__main">
				<div class="search-form__search-box-wrapper">
					<SearchBox ref="searchBox"
						:value.sync="searchText"
						:placeholder-text="t('spreed', 'Search messages …')"
						:is-focused.sync="isFocused"
						@input="debounceFetchSearchResults" />
					<NcButton :pressed.sync="searchDetailsOpened"
						:aria-label="t('spreed', 'Search options')"
						:title="t('spreed', 'Search options')"
						type="tertiary-no-background">
						<template #icon>
							<IconFilter :size="15" />
						</template>
					</NcButton>
				</div>
				<TransitionWrapper name="radial-reveal">
					<div v-show="searchDetailsOpened" class="search-form__search-detail">
						<NcSelect v-model="fromUser"
							class="search-form__search-detail__from-user"
							:aria-label-combobox="t('spreed', 'From User')"
							:placeholder="t('spreed', 'From User')"
							user-select
							:loading="!participantsInitialised"
							:options="participants"
							@update:modelValue="debounceFetchSearchResults" />
						<div class="search-form__search-detail__date-picker-wrapper">
							<NcDateTimePickerNative id="search-form__search-detail__date-picker--since"
								v-model="sinceDate"
								class="search-form__search-detail__date-picker"
								format="YYYY-MM-DD"
								type="date"
								:step="1"
								:aria-label="t('spreed', 'Since')"
								:label="t('spreed', 'Since')"
								:minute-step="1"
								@update:modelValue="debounceFetchSearchResults" />
							<NcDateTimePickerNative id="search-form__search-detail__date-picker--until"
								v-model="untilDate"
								class="search-form__search-detail__date-picker"
								format="YYYY-MM-DD"
								type="date"
								:step="1"
								:aria-label="t('spreed', 'Until')"
								:label="t('spreed', 'Until')"
								:minute-step="1"
								@update:modelValue="debounceFetchSearchResults" />
						</div>
					</div>
				</TransitionWrapper>
			</div>
		</div>
		<div class="search-results">
			<template v-if="searchResults.length !== 0">
				<NcListItem v-for="item of searchResults"
					:key="`message_${item.attributes.messageId}`"
					:data-nav-id="`message_${item.attributes.messageId}`"
					:name="item.title"
					:v-tooltip="item.subline"
					@click="onClickMessageSearchResult(item.attributes)">
					<template #icon>
						<AvatarWrapper :id="item.attributes.actorId"
							:name="item.title"
							:source="item.attributes.actorType"
							:disable-menu="true"
							token="new" />
					</template>
					<template #subname>
						{{ item.subline }}
					</template>
					<template #details>
						<NcDateTime :timestamp="parseInt(item.attributes.timestamp) * 1000"
							class="search-results__date"
							relative-time="narrow"
							ignore-seconds />
					</template>
				</NcListItem>
			</template>
			<NcEmptyContent v-else-if="!isFetchingResults && searchText.trim().length !== 0"
				class="search-results__empty"
				:name="t('spreed', 'No results found')">
				<template #icon>
					<IconMessageOutline :size="64" />
				</template>
			</NcEmptyContent>
			<template v-if="canLoadMore">
				<NcButton wide type="tertiary" @click="fetchSearchResults(false)">
					{{ t('spreed', 'Load more results') }}
				</NcButton>
			</template>
			<template v-if="isFetchingResults">
				<NcLoadingIcon class="search-results__loading" />
			</template>
		</div>
	</div>
</template>

<style lang="scss" scoped>
.search-messages-tab {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.search-form {
	display: flex;
	flex-direction: column;
	padding-bottom: var(--default-grid-baseline);

	&__search-box-wrapper {
		display: flex;
		gap: var(--default-grid-baseline);
	}

	&__main {
		display: flex;
		flex-direction: column;
		flex: 1;
	}

	&__search-detail {
		display: flex;
		flex-direction: column;
		width: 100%;
		margin-top: calc(var(--default-grid-baseline) * 4);

		&__from-user {
			margin-top: 8px;
			:deep(.vs__dropdown-toggle) {
				overflow-y: clip;
			}
		}

		&__date-picker {
			width: 100%;
			min-width: 100px;

			&-wrapper {
				display: flex;
				gap: var(--default-grid-baseline);
			}
		}
	}
}

.search-results {
	transition: all 0.15s ease;
	height: 100%;
	overflow-y: auto;

	&__date {
		font-size: x-small;
	}

	&__loading {
		height: var(--default-clickable-area);
	}

	&__empty {
		height: 100%;
	}
}
</style>
