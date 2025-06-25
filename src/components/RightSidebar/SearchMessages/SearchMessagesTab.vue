<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { RouteLocation } from 'vue-router'
import type {
	IUserData,
	Participant,
	SearchMessagePayload,
	UnifiedSearchResponse,
	UnifiedSearchResultEntry,
} from '../../../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useStore } from 'vuex'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import IconCalendarRange from 'vue-material-design-icons/CalendarRange.vue'
import IconFilter from 'vue-material-design-icons/Filter.vue'
import IconMessageOutline from 'vue-material-design-icons/MessageOutline.vue'
import SearchBox from '../../UIShared/SearchBox.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'
import SearchMessageItem from './SearchMessageItem.vue'
import { useArrowNavigation } from '../../../composables/useArrowNavigation.js'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { useIsInCall } from '../../../composables/useIsInCall.js'
import { ATTENDEE } from '../../../constants.ts'
import { searchMessages } from '../../../services/coreService.ts'
import { EventBus } from '../../../services/EventBus.ts'
import CancelableRequest from '../../../utils/cancelableRequest.js'

const props = defineProps<{
	isActive: boolean
}>()
const emit = defineEmits<{
	(event: 'close'): void
}>()

const searchMessagesTab = ref<HTMLElement | null>(null)
const searchBox = ref<InstanceType<typeof SearchBox> | null>(null)
const { initializeNavigation, resetNavigation } = useArrowNavigation(searchMessagesTab, searchBox)

const isFocused = ref(false)
const searchResults = ref<(UnifiedSearchResultEntry &
	{
		to: {
			name: string
			hash: string
			params: {
				token: string
				skipLeaveWarning: boolean
			}
		}
	})[]>([])
const searchText = ref('')
const fromUser = ref<IUserData | undefined>(undefined)
const sinceDate = ref<Date | null>(null)
const untilDate = ref<Date | null>(null)
const searchLimit = ref(10)
const searchCursor = ref<number | string | null>(0)
const searchDetailsOpened = ref(false)
const isFetchingResults = ref(false)
const isSearchExhausted = ref(false)

const store = useStore()
const isInCall = useIsInCall()

const token = useGetToken()
const participantsInitialised = computed(() => store.getters.participantsInitialised(token.value))
const participants = computed<IUserData[]>(() => {
	return store.getters.participantsList(token.value)
		.filter(({ actorType }: Participant) => actorType === ATTENDEE.ACTOR_TYPE.USERS) // FIXME: federated users are not supported by the search provider
		.map(({ actorId, displayName, actorType }: { actorId: string, displayName: string, actorType: string }) => ({
			id: actorId,
			displayName,
			isNoUser: actorType !== 'users',
			user: actorId,
			disableMenu: true,
			showUserStatus: false,
		}))
})
const canLoadMore = computed(() => !isSearchExhausted.value && !isFetchingResults.value && searchCursor.value !== 0)
const hasFilter = computed(() => fromUser.value || sinceDate.value || untilDate.value)

watch(() => props.isActive, (isActive) => {
	if (isActive) {
		// NcAppSidebarTabs renders tabs in 2 ticks, so need to wait here
		nextTick(() => searchBox.value!.focus())
	}
}, { immediate: true })

onMounted(() => {
	EventBus.on('route-change', onRouteChange)
})

onBeforeUnmount(() => {
	EventBus.off('route-change', onRouteChange)
	abortSearch()
})

const onRouteChange = ({ from, to }: { from: RouteLocation, to: RouteLocation }): void => {
	if (to.name !== 'conversation' || from.params.token !== to.params.token || (to.hash && isInCall.value)) {
		abortSearch()
		emit('close')
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
	cancelSearchFn()
	searchText.value = ''
	fromUser.value = undefined
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

let cancelSearchFn = () => {}

type SearchMessageCancelableRequest = {
	request: (payload: SearchMessagePayload) => UnifiedSearchResponse
	cancel: () => void
}

/**
 * @param [isNew=true] Is it a new search (search parameters changed)?
 * Fetch the search results from the server
 */
async function fetchSearchResults(isNew = true): Promise<void> {
	const term = searchText.value.trim()
	// Don't search if the search text is empty
	if (term.length === 0) {
		return
	}

	isFetchingResults.value = true

	try {
		// cancel the previous search request and reset the navigation
		cancelSearchFn()
		resetNavigation()

		const { request, cancel } = CancelableRequest(searchMessages) as SearchMessageCancelableRequest
		cancelSearchFn = cancel

		if (isNew) {
			searchCursor.value = 0
		}
		if (searchCursor.value === 0) {
			searchResults.value = []
		}

		if (term.length === 0 && !fromUser.value && !sinceDate.value && !untilDate.value) {
			return
		}
		const response = await request({
			term,
			person: fromUser.value?.id,
			since: sinceDate.value?.toISOString(),
			until: untilDate.value?.toISOString(),
			limit: searchLimit.value,
			cursor: searchCursor.value || null,
			from: `/call/${token.value}`,
		})

		const data = response?.data?.ocs?.data
		if (data && data.entries.length > 0) {
			let entries = data.entries as UnifiedSearchResultEntry[]

			isSearchExhausted.value = entries.length < searchLimit.value
			searchCursor.value = data.cursor

			// FIXME: remove the filter after the person filter is fixed on the server
			if (fromUser.value) {
				entries = entries.filter((entry) => entry.attributes.actorId === fromUser.value?.id)
				if (entries.length === 0 && !isSearchExhausted.value) {
					return await loadMore()
				}
			}

			searchResults.value = searchResults.value.concat(entries.map((entry: UnifiedSearchResultEntry) => {
				return {
					...entry,
					to: {
						name: 'conversation',
						hash: `#message_${entry.attributes.messageId}`,
						params: {
							token: entry.attributes.conversation,
							skipLeaveWarning: true,
						},
					},
				}
			}))
			nextTick(() => initializeNavigation())
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

const debounceFetchSearchResults = debounce(fetchNewSearchResult, 250)

watch([searchText, fromUser, sinceDate, untilDate], debounceFetchSearchResults)
</script>

<template>
	<div ref="searchMessagesTab" class="search-messages-tab">
		<div class="search-form">
			<div class="search-form__main">
				<div class="search-form__search-box-wrapper">
					<SearchBox ref="searchBox"
						v-model:value="searchText"
						v-model:is-focused="isFocused"
						:placeholder-text="t('spreed', 'Search messages …')" />
					<NcButton
						v-model:pressed="searchDetailsOpened"
						:aria-label="t('spreed', 'Search options')"
						:title="t('spreed', 'Search options')"
						variant="tertiary-no-background">
						<template #icon>
							<IconFilter :size="15" />
						</template>
					</NcButton>
				</div>
				<TransitionWrapper name="radial-reveal">
					<div v-show="searchDetailsOpened" class="search-form__search-detail">
						<NcSelectUsers v-model="fromUser"
							class="search-form__search-detail__from-user"
							:aria-label-combobox="t('spreed', 'From User')"
							:placeholder="t('spreed', 'From User')"
							:loading="!participantsInitialised"
							:options="participants" />
						<div class="search-form__search-detail__date-picker-wrapper">
							<NcDateTimePickerNative id="search-form__search-detail__date-picker--since"
								v-model="sinceDate"
								class="search-form__search-detail__date-picker"
								format="YYYY-MM-DD"
								type="date"
								:step="1"
								:max="new Date()"
								:aria-label="t('spreed', 'Since')"
								:label="t('spreed', 'Since')" />
							<NcDateTimePickerNative id="search-form__search-detail__date-picker--until"
								v-model="untilDate"
								class="search-form__search-detail__date-picker"
								format="YYYY-MM-DD"
								type="date"
								:max="new Date()"
								:aria-label="t('spreed', 'Until')"
								:label="t('spreed', 'Until')"
								:minute-step="1" />
						</div>
					</div>
				</TransitionWrapper>
				<TransitionWrapper name="fade">
					<div v-show="hasFilter && !searchDetailsOpened"
						class="search-form__search-bubbles">
						<NcChip v-if="fromUser"
							variant="tertiary"
							:text="fromUser.displayName"
							@close="fromUser = undefined">
							<template #icon>
								<NcAvatar :size="24"
									:user="fromUser.id"
									:display-name="fromUser.displayName"
									hide-status />
							</template>
						</NcChip>
						<NcChip v-if="sinceDate"
							variant="tertiary"
							:text="t('spreed', 'Since') + ' ' + sinceDate?.toLocaleDateString()"
							@close="sinceDate = null">
							<template #icon>
								<IconCalendarRange :size="15" />
							</template>
						</NcChip>
						<NcChip v-if="untilDate"
							variant="tertiary"
							:text="t('spreed', 'Until') + ' ' + untilDate?.toLocaleDateString()"
							@close="untilDate = null">
							<template #icon>
								<IconCalendarRange :size="15" />
							</template>
						</NcChip>
					</div>
				</TransitionWrapper>
			</div>
		</div>
		<div class="search-results">
			<template v-if="searchResults.length !== 0">
				<SearchMessageItem v-for="item of searchResults"
					:key="`message_${item.attributes.messageId}`"
					:message-id="item.attributes.messageId"
					:title="item.title"
					:subline="item.subline"
					:actor-id="item.attributes.actorId"
					:actor-type="item.attributes.actorType"
					:token="item.attributes.conversation"
					:timestamp="item.attributes.timestamp"
					:to="item.to" />
			</template>
			<NcEmptyContent v-else-if="!isFetchingResults && searchText.trim().length !== 0"
				class="search-results__empty"
				:name="t('spreed', 'No results found')">
				<template #icon>
					<IconMessageOutline :size="64" />
				</template>
			</NcEmptyContent>
			<template v-if="canLoadMore">
				<NcButton wide variant="tertiary" @click="fetchSearchResults(false)">
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

	&__search-bubbles {
		display: flex;
		flex-wrap: wrap;
		gap: var(--default-grid-baseline);
		margin-top: var(--default-grid-baseline);
	}
}

.search-results {
	transition: all 0.15s ease;
	height: 100%;
	overflow-y: auto;

	&__loading {
		height: var(--default-clickable-area);
	}

	&__empty {
		height: 100%;
	}
}
</style>
