<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="search-messages-tab">
		<div class="search-form">
			<div class="search-form__search-box-wrapper">
				<div class="search-form__main">
					<SearchBox ref="searchBox"
						:value.sync="searchText"
						:is-focused.sync="isFocused"
						@input="debounceFetchSearchResults"
						@abort-search="clearSearchText" />
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
							<NcDateTimePicker v-model="sinceDate"
								class="search-form__search-detail__date-picker"
								type="datetime"
								format="YYYY-MM-DD HH:mm"
								clearable
								:aria-label="t('spreed', 'Since')"
								:placeholder="t('spreed', 'Since')"
								:disabled-date="notAfterUntilDate"
								:disabled-time="notAfterUntilTime"
								:minute-step="1"
								@update:modelValue="debounceFetchSearchResults" />
							<NcDateTimePicker v-model="untilDate"
								class="search-form__search-detail__date-picker"
								type="datetime"
								format="YYYY-MM-DD HH:mm"
								clearable
								:aria-label="t('spreed', 'Until')"
								:placeholder="t('spreed', 'Until')"
								:disabled-date="notBeforeSinceDate"
								:disabled-time="notBeforeSinceTime"
								:minute-step="1"
								@update:modelValue="debounceFetchSearchResults" />
						</div>
					</TransitionWrapper>
				</div>
				<NcButton :pressed.sync="searchDetailsOpened"
					aria-label="Search Detail"
					type="tertiary-no-background">
					<template #icon>
						<DotsHorizontal />
					</template>
				</NcButton>
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
							token="new"
							:show-user-status="true" />
					</template>
					<template #subname>
						{{ item.subline }}
					</template>
					<template #details>
						<NcDateTime :timestamp="item.attributes.timestamp * 1000"
							class="search-results__date"
							relative-time="narrow"
							ignore-seconds />
					</template>
				</NcListItem>
			</template>
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

<script>
import debounce from 'debounce'
import { ref } from 'vue'

import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDateTime from '@nextcloud/vue/dist/Components/NcDateTime.js'
import NcDateTimePicker from '@nextcloud/vue/dist/Components/NcDateTimePicker.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import SearchBox from '../../UIShared/SearchBox.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'

import { EventBus } from '../../../services/EventBus.ts'
import { searchMessages } from '../../../services/messagesService.ts'
import CancelableRequest from '../../../utils/cancelableRequest.js'

export default {
	name: 'SearchMessagesTab',
	components: {
		NcButton,
		NcDateTime,
		NcDateTimePicker,
		NcListItem,
		NcLoadingIcon,
		NcSelect,
		AvatarWrapper,
		SearchBox,
		TransitionWrapper,
		DotsHorizontal,
	},

	props: {
		isActive: {
			type: Boolean,
			required: true,
		}
	},

	setup() {
		const searchBox = ref(null)

		return {
			searchBox,
		}
	},

	data() {
		return {
			searchText: '',
			isFocused: false,
			searchResults: [],
			debounceFetchSearchResults: () => {},
			cancelSearch: () => {},
			fromUser: null,
			sinceDate: null,
			untilDate: null,
			searchDetailsOpened: false,
			isFetchingResults: false,
			isSearchExhausted: false,
			searchLimit: 10,
			searchCursor: 0,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		participantsInitialised() {
			return this.$store.getters.participantsInitialised(this.token)
		},

		participants() {
			return this.$store.getters.participantsList(this.token)
				.map(({
					 actorId,
					 displayName,
					 actorType,
				}) => ({
					 id: actorId,
					 displayName,
					 isNoUser: actorType !== 'users',
					 subname: actorId,
					 user: actorId,
					 disableMenu: true,
					 showUserStatus: false,
				}))
		},

		canLoadMore() {
			return !this.isSearchExhausted && !this.isFetchingResults && this.searchCursor !== 0
		}
	},

	mounted() {
		this.debounceFetchSearchResults = debounce(this.fetchNewSearchResults, 250)
		EventBus.on('route-change', this.onRouteChange)
	},

	beforeDestroy() {
		this.debounceFetchSearchResults.clear?.()
		EventBus.off('route-change', this.onRouteChange)
	},

	methods: {
		t,

		onRouteChange({ from, to }) {
			if (from.name === 'conversation'
				&& to.name === 'conversation'
				&& from.params.token === to.params.token) {
				return
			}
			if (to.name === 'conversation') {
				this.abortSearch()
			}
		},

		abortSearch() {
			this.clearSearchText()
			this.fromUser = null
			this.sinceDate = null
			this.untilDate = null
			this.searchDetailsOpened = false
			this.searchResults = []
			this.searchCursor = 0
		},

		clearSearchText() {
			this.searchText = ''
		},

		loadMore() {
			this.fetchSearchResults(false)
		},

		fetchNewSearchResults() {
			return this.fetchSearchResults()
		},

		async fetchSearchResults(isNew = true) {
			this.isFetchingResults = true

			try {
				this.cancelSearch('canceled')
				const { request, cancel } = CancelableRequest(searchMessages)
				this.cancelSearch = cancel

				if (isNew) {
					this.searchCursor = 0
				}
				if (this.searchCursor === 0) {
					this.searchResults = []
				}

				const term = this.searchText.trim()
				if (term.length === 0 && !this.fromUser && !this.sinceDate && !this.untilDate) {
					return
				}
				const response = await request({
					term: term.length !== 0 ? term : null,
					person: this.fromUser?.id,
					since: this.sinceDate?.toISOString(),
					until: this.untilDate?.toISOString(),
					limit: this.searchLimit,
					cursor: this.searchCursor || null,
					conversation: this.token,
				})

				const data = response?.data?.ocs?.data
				if (data?.entries) {
					let entries = data?.entries

					this.isSearchExhausted = entries.length < this.searchLimit
					this.searchCursor = data.cursor

					// FIXME: remove the filter after searching from person is fixed on the server
					if (this.fromUser) {
						entries = entries.filter((entry) => entry.attributes.actorId === this.fromUser.id)
						if (entries.length === 0 && !this.isSearchExhausted) {
							return await this.fetchSearchResults(false)
						}
					}

					this.searchResults = this.searchResults.concat(entries)
				}
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error('Error searching for messages', exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			} finally {
				this.isFetchingResults = false
			}
		},

		setFromUser(actorId) {
			this.fromUser = this.fromUser !== actorId ? actorId : null
		},

		notBeforeSinceDate(date) {
			return this.sinceDate ? date.getDate() < this.sinceDate.getDate() : false
		},

		notAfterUntilDate(date) {
			return date.getDate() > (this.untilDate?.getDate() ?? new Date().getDate())
		},

		notBeforeSinceTime(date) {
			return this.sinceDate ? date.valueOf() < this.sinceDate?.valueOf() : false
		},

		notAfterUntilTime(date) {
			return date.valueOf() > (this.untilDate?.valueOf() ?? Date.now())
		},

		onClickMessageSearchResult({ messageId, conversation }) {
			this.$router.push({
				name: 'conversation',
				hash: `#message_${messageId}`,
				params: {
					token: conversation,
				},
			}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},
	}
}
</script>

<style lang="scss" scoped>
.search-messages-tab {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.search-form {
	display: flex;
	flex-direction: column;

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

		&__from-user {
			margin-top: 8px;
			:deep(.vs__dropdown-toggle) {
				overflow-y: clip;
			}
		}

		&__date-picker {
			width: 100%;
		}
	}
}

.search-results {
	transition: all 0.15s ease;
	height: 100%;

	&__date {
		font-size: x-small;
	}

	&__loading {
		height: var(--default-clickable-area);
	}
}
</style>
