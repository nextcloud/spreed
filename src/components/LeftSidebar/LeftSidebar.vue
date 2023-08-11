<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<NcAppNavigation ref="leftSidebar" :aria-label="t('spreed', 'Conversation list')">
		<div class="new-conversation"
			:class="{ 'new-conversation--scrolled-down': !isScrolledToTop }">
			<div class="conversations-search"
				:class="{'conversations-search--expanded': isFocused}">
				<SearchBox ref="searchBox"
					:value.sync="searchText"
					:is-focused="isFocused"
					@focus="setIsFocused"
					@blur="setIsFocused"
					@trailing-blur="setIsFocused"
					@input="debounceFetchSearchResults"
					@abort-search="abortSearch" />
			</div>

			<TransitionWrapper name="radial-reveal" group>
				<!-- Filters -->
				<div v-show="searchText === ''"
					key="filters"
					class="filters"
					:class="{'hidden-visually': isFocused}">
					<NcActions class="filter-actions"
						:primary="isFiltered !== null">
						<template #icon>
							<FilterIcon :size="15" />
						</template>
						<NcActionButton close-after-click
							class="filter-actions__button"
							:class="{'filter-actions__button--active': isFiltered === 'mentions'}"
							@click="handleFilter('mentions')">
							<template #icon>
								<AtIcon :size="20" />
							</template>
							{{ t('spreed','Filter unread mentions') }}
						</NcActionButton>

						<NcActionButton close-after-click
							class="filter-actions__button"
							:class="{'filter-actions__button--active': isFiltered === 'unread'}"
							@click="handleFilter('unread')">
							<template #icon>
								<MessageBadge :size="20" />
							</template>
							{{ t('spreed','Filter unread messages') }}
						</NcActionButton>

						<NcActionButton v-if="isFiltered"
							close-after-click
							class="filter-actions__clearbutton"
							@click="handleFilter(null)">
							<template #icon>
								<FilterRemoveIcon :size="20" />
							</template>
							{{ t('spreed', 'Clear filters') }}
						</NcActionButton>
					</NcActions>
				</div>

				<!-- Actions -->
				<div v-show="searchText === ''"
					key="actions"
					class="actions"
					:class="{'hidden-visually': isFocused}">
					<NcActions class="conversations-actions">
						<template #icon>
							<DotsVertical :size="20" />
						</template>
						<NcActionButton v-if="canStartConversations"
							close-after-click
							@click="showModalNewConversation">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('spreed','Create a new conversation') }}
						</NcActionButton>

						<NcActionButton close-after-click
							@click="showModalListConversations">
							<template #icon>
								<List :size="20" />
							</template>
							{{ t('spreed','Join open conversations') }}
						</NcActionButton>
					</NcActions>
				</div>
			</TransitionWrapper>

			<!-- All open conversations list -->
			<OpenConversationsList ref="openConversationsList" />

			<!-- New Conversation dialog-->
			<NewGroupConversation ref="newGroupConversation" />
		</div>

		<template #list>
			<li ref="container" class="left-sidebar__list" @scroll="debounceHandleScroll">
				<ul class="scroller">
					<NcListItem v-if="noMatchFound && searchText"
						:title="t('spreed', 'Create a new conversation')"
						@click="createConversation(searchText)">
						<template #icon>
							<ChatPlus :size="30" />
						</template>
						<template #subtitle>
							{{ searchText }}
						</template>
					</NcListItem>

					<NcAppNavigationCaption :class="{'hidden-visually': !isSearching}"
						:title="t('spreed', 'Conversations')" />
					<Conversation v-for="item of conversationsList"
						:key="item.id"
						:ref="`conversation-${item.token}`"
						:item="item" />
					<template v-if="!initialisedConversations">
						<LoadingPlaceholder type="conversations" />
					</template>
					<Hint v-else-if="noMatchFound"
						:hint="t('spreed', 'No matches found')" />
					<template v-if="isSearching">
						<template v-if="!listedConversationsLoading && searchResultsListedConversations.length > 0">
							<NcAppNavigationCaption :title="t('spreed', 'Open conversations')" />
							<Conversation v-for="item of searchResultsListedConversations"
								:key="item.id"
								:item="item"
								is-search-result />
						</template>
						<template v-if="searchResultsUsers.length !== 0">
							<NcAppNavigationCaption :title="t('spreed', 'Users')" />
							<NcListItem v-for="item of searchResultsUsers"
								:key="item.id"
								:title="item.label"
								@click="createAndJoinConversation(item)">
								<template #icon>
									<ConversationIcon :item="iconData(item)"
										:disable-menu="true" />
								</template>
							</NcListItem>
						</template>
						<template v-if="!showStartConversationsOptions">
							<NcAppNavigationCaption v-if="searchResultsUsers.length === 0"
								:title="t('spreed', 'Users')" />
							<Hint v-if="contactsLoading" :hint="t('spreed', 'Loading')" />
							<Hint v-else :hint="t('spreed', 'No matches found')" />
						</template>
					</template>
					<template v-if="showStartConversationsOptions">
						<template v-if="searchResultsGroups.length !== 0">
							<NcAppNavigationCaption :title="t('spreed', 'Groups')" />
							<NcListItem v-for="item of searchResultsGroups"
								:key="item.id"
								:title="item.label"
								@click="createAndJoinConversation(item)">
								<template #icon>
									<ConversationIcon :item="iconData(item)"
										:disable-menu="true" />
								</template>
							</NcListItem>
						</template>

						<template v-if="searchResultsCircles.length !== 0">
							<NcAppNavigationCaption :title="t('spreed', 'Circles')" />
							<NcListItem v-for="item of searchResultsCircles"
								:key="item.id"
								:title="item.label"
								@click="createAndJoinConversation(item)">
								<template #icon>
									<ConversationIcon :item="iconData(item)"
										:disable-menu="true" />
								</template>
							</NcListItem>
						</template>

						<NcAppNavigationCaption v-if="sourcesWithoutResults"
							:title="sourcesWithoutResultsList" />
						<Hint v-if="contactsLoading" :hint="t('spreed', 'Loading')" />
						<Hint v-else :hint="t('spreed', 'No search results')" />
					</template>
				</ul>
			</li>
			<NcButton v-if="!preventFindingUnread && unreadNum > 0"
				class="unread-mention-button"
				type="primary"
				@click="scrollBottomUnread">
				{{ t('spreed', 'Unread mentions') }}
			</NcButton>
		</template>

		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<NcButton class="settings-button" @click="showSettings">
						{{ t('spreed', 'Talk settings') }}
					</NcButton>
				</div>
			</div>
		</template>
	</NcAppNavigation>
</template>

<script>
import debounce from 'debounce'
import { ref } from 'vue'

import AtIcon from 'vue-material-design-icons/At.vue'
import ChatPlus from 'vue-material-design-icons/ChatPlus.vue'
import DotsVertical from 'vue-material-design-icons/DotsVertical.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import FilterRemoveIcon from 'vue-material-design-icons/FilterRemove.vue'
import List from 'vue-material-design-icons/FormatListBulleted.vue'
import MessageBadge from 'vue-material-design-icons/MessageBadge.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'

import ConversationIcon from '../ConversationIcon.vue'
import Hint from '../Hint.vue'
import LoadingPlaceholder from '../LoadingPlaceholder.vue'
import TransitionWrapper from '../TransitionWrapper.vue'
import Conversation from './ConversationsList/Conversation.vue'
import NewGroupConversation from './NewGroupConversation/NewGroupConversation.vue'
import OpenConversationsList from './OpenConversationsList/OpenConversationsList.vue'
import SearchBox from './SearchBox/SearchBox.vue'

import { useArrowNavigation } from '../../composables/useArrowNavigation.js'
import { CONVERSATION } from '../../constants.js'
import {
	createPrivateConversation,
	searchPossibleConversations,
	searchListedConversations,
} from '../../services/conversationsService.js'
import { EventBus } from '../../services/EventBus.js'
import CancelableRequest from '../../utils/cancelableRequest.js'

export default {

	name: 'LeftSidebar',

	components: {
		NcAppNavigation,
		NcAppNavigationCaption,
		NcButton,
		Hint,
		SearchBox,
		NewGroupConversation,
		OpenConversationsList,
		Conversation,
		LoadingPlaceholder,
		NcListItem,
		ConversationIcon,
		NcActions,
		NcActionButton,
		TransitionWrapper,
		// Icons
		AtIcon,
		MessageBadge,
		FilterIcon,
		FilterRemoveIcon,
		Plus,
		ChatPlus,
		List,
		DotsVertical,
	},

	mixins: [
		isMobile,
	],

	setup() {
		const leftSidebar = ref(null)
		const searchBox = ref(null)

		const { initializeNavigation } = useArrowNavigation(leftSidebar, searchBox)

		return {
			initializeNavigation,
			leftSidebar,
			searchBox,
		}
	},

	data() {
		return {
			searchText: '',
			searchResults: [],
			searchResultsUsers: [],
			searchResultsGroups: [],
			searchResultsCircles: [],
			searchResultsListedConversations: [],
			contactsLoading: false,
			listedConversationsLoading: false,
			isCirclesEnabled: loadState('spreed', 'circles_enabled'),
			canStartConversations: loadState('spreed', 'start_conversations'),
			initialisedConversations: false,
			cancelSearchPossibleConversations: () => {},
			cancelSearchListedConversations: () => {},
			// Keeps track of whether the conversation list is scrolled to the top or not
			isScrolledToTop: true,
			refreshTimer: null,
			unreadNum: 0,
			firstUnreadPos: 0,
			preventFindingUnread: false,
			roomListModifiedBefore: 0,
			forceFullRoomListRefreshAfterXLoops: 0,
			isFocused: false,
			isFiltered: null,
		}
	},

	computed: {
		conversationsList() {
			let conversations = this.$store.getters.conversationsList
			if (this.searchText !== '' || this.isFocused) {
				const lowerSearchText = this.searchText.toLowerCase()
				conversations = conversations.filter(conversation =>
					conversation.displayName.toLowerCase().includes(lowerSearchText)
							|| conversation.name.toLowerCase().includes(lowerSearchText)
				)
			} else if (this.isFiltered === 'unread') {
				conversations = conversations.filter(conversation => conversation.unreadMessages > 0)
			} else if (this.isFiltered === 'mentions') {
				conversations = conversations.filter(conversation => conversation.unreadMention || (conversation.unreadMessages > 0
					&& (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE || conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER)))
			}

			// FIXME: this modifies the original array,
			// maybe should act on a copy or sort already within the store ?
			return conversations.sort(this.sortConversations)
		},

		isSearching() {
			return this.searchText !== ''
		},

		noMatchFound() {
			return (this.searchText || this.isFiltered) && !this.conversationsList.length
		},

		showStartConversationsOptions() {
			return this.isSearching && this.canStartConversations
		},

		sourcesWithoutResults() {
			return !this.searchResultsUsers.length
				|| !this.searchResultsGroups.length
				|| (this.isCirclesEnabled && !this.searchResultsCircles.length)
		},

		sourcesWithoutResultsList() {
			const hasNoResultsUsers = !this.searchResultsUsers.length
			const hasNoResultsGroups = !this.searchResultsGroups.length
			const hasNoResultsCircles = this.isCirclesEnabled && !this.searchResultsCircles.length

			if (hasNoResultsUsers) {
				if (hasNoResultsGroups) {
					return (hasNoResultsCircles)
						? t('spreed', 'Users, groups and circles')
						: t('spreed', 'Users and groups')
				} else {
					return (hasNoResultsCircles)
						? t('spreed', 'Users and circles')
						: t('spreed', 'Users')
				}
			} else {
				if (hasNoResultsGroups) {
					return (hasNoResultsCircles)
						? t('spreed', 'Groups and circles')
						: t('spreed', 'Groups')
				} else {
					return (hasNoResultsCircles)
						? t('spreed', 'Circles')
						: t('spreed', 'Other sources')
				}
			}
		},
	},

	beforeMount() {
		// After a conversation was created, the search filter is reset
		EventBus.$once('resetSearchFilter', () => {
			this.abortSearch()
		})

		this.fetchConversations()
	},

	mounted() {
		// Refreshes the conversations every 30 seconds
		this.refreshTimer = window.setInterval(() => {
			if (!this.isFetchingConversations) {
				this.fetchConversations()
			}
		}, 30000)

		EventBus.$on('should-refresh-conversations', this.handleShouldRefreshConversations)
		EventBus.$once('conversations-received', this.handleUnreadMention)
		EventBus.$on('route-change', this.onRouteChange)
		EventBus.$on('joined-conversation', this.handleJoinedConversation)
	},

	beforeDestroy() {
		EventBus.$off('should-refresh-conversations', this.handleShouldRefreshConversations)
		EventBus.$off('conversations-received', this.handleUnreadMention)
		EventBus.$off('route-change', this.onRouteChange)

		this.cancelSearchPossibleConversations()
		this.cancelSearchPossibleConversations = null

		this.cancelSearchListedConversations()
		this.cancelSearchListedConversations = null

		if (this.refreshTimer) {
			clearInterval(this.refreshTimer)
			this.refreshTimer = null
		}
	},

	methods: {
		showModalNewConversation() {
			this.$refs.newGroupConversation.showModal()
		},

		showModalListConversations() {
			this.$refs.openConversationsList.showModal()
		},

		setIsFocused(event) {
			if (this.searchText !== '') {
				return
			}
			this.isFocused = event.type === 'focus'
		},

		handleFilter(filter) {
			this.isFiltered = filter
			// Clear the search input once a filter is active
			this.searchText = ''
		},

		scrollBottomUnread() {
			this.preventFindingUnread = true
			this.$refs.container.scrollTo({
				top: this.firstUnreadPos - 150,
				behavior: 'smooth',
			})
			setTimeout(() => {
				this.handleUnreadMention()
				this.preventFindingUnread = false
			}, 500)
		},
		debounceFetchSearchResults: debounce(function() {
			if (this.isSearching) {
				this.fetchSearchResults()
			}
		}, 250),

		async fetchPossibleConversations() {
			this.contactsLoading = true

			try {
				// FIXME: move to conversationsStore
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(searchPossibleConversations)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({
					searchText: this.searchText,
					token: undefined,
					onlyUsers: !this.canStartConversations,
				})

				this.searchResults = response?.data?.ocs?.data || []
				this.searchResultsUsers = this.searchResults.filter((match) => {
					return match.source === 'users'
						&& match.id !== this.$store.getters.getUserId()
						&& !this.hasOneToOneConversationWith(match.id)
				})
				this.searchResultsGroups = this.searchResults.filter((match) => match.source === 'groups')
				this.searchResultsCircles = this.searchResults.filter((match) => match.source === 'circles')
				this.contactsLoading = false
				this.$nextTick(() => {
					this.initializeNavigation('.list-item')
				})
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error('Error searching for possible conversations', exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		async fetchListedConversations() {
			try {
				this.listedConversationsLoading = true

				// FIXME: move to conversationsStore
				this.cancelSearchListedConversations('canceled')
				const { request, cancel } = CancelableRequest(searchListedConversations)
				this.cancelSearchListedConversations = cancel

				const response = await request({ searchText: this.searchText })
				this.searchResultsListedConversations = response.data.ocs.data
				this.listedConversationsLoading = false
				this.$nextTick(() => {
					this.initializeNavigation('.list-item')
				})
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error('Error searching for open conversations', exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		async fetchSearchResults() {
			await Promise.all([this.fetchPossibleConversations(), this.fetchListedConversations()])
		},

		/**
		 * Create a new conversation with the selected user
		 * or bring up the dialog to create a new group/circle conversation
		 *
		 * @param {object} item The autocomplete suggestion to start a conversation with
		 * @param {string} item.id The ID of the target
		 * @param {string} item.label The displayname of the target
		 * @param {string} item.source The source of the target (e.g. users, groups, circle)
		 */
		async createAndJoinConversation(item) {
			if (item.source === 'users') {
				// Create one-to-one conversation directly
				const conversation = await this.$store.dispatch('createOneToOneConversation', item.id)
				this.$router.push({
					name: 'conversation',
					params: { token: conversation.token },
				}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			} else {
				// For other types, show the modal directly
				this.$refs.newGroupConversation.showModalForItem(item)
			}
		},

		async createConversation(name) {
			const response = await createPrivateConversation(name)
			const conversation = response.data.ocs.data
			this.$store.dispatch('addConversation', conversation)
			this.abortSearch()
			this.$router.push({
				name: 'conversation',
				params: { token: conversation.token },
			}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		hasOneToOneConversationWith(userId) {
			return !!this.conversationsList.find(conversation => conversation.type === CONVERSATION.TYPE.ONE_TO_ONE && conversation.name === userId)
		},

		// Reset the search text, therefore end the search operation.
		abortSearch() {
			this.searchText = ''
			this.isFocused = false
			if (this.cancelSearchPossibleConversations) {
				this.cancelSearchPossibleConversations()
			}
			if (this.cancelSearchListedConversations) {
				this.cancelSearchListedConversations()
			}
		},

		showSettings() {
			// FIXME: use local EventBus service instead of the global one
			emit('show-settings')
		},

		sortConversations(conversation1, conversation2) {
			if (conversation1.isFavorite !== conversation2.isFavorite) {
				return conversation1.isFavorite ? -1 : 1
			}

			return conversation2.lastActivity - conversation1.lastActivity
		},

		/**
		 * @param {object} [options] Options for conversation refreshing
		 * @param {string} [options.token] The conversation token that got update
		 * @param {object} [options.properties] List of changed properties
		 * @param {boolean} [options.all] Whether all conversations should be fetched
		 */
		async handleShouldRefreshConversations(options) {
			if (options?.all === true) {
				this.roomListModifiedBefore = 0
			} else if (options?.token && options?.properties) {
				await this.$store.dispatch('setConversationProperties', {
					token: options.token,
					properties: options.properties,
				})
			}

			this.debounceFetchConversations()
		},

		debounceFetchConversations: debounce(function() {
			if (!this.isFetchingConversations) {
				this.fetchConversations()
			}
		}, 3000),

		async fetchConversations() {
			this.isFetchingConversations = true
			if (this.forceFullRoomListRefreshAfterXLoops === 0) {
				this.roomListModifiedBefore = 0
				this.forceFullRoomListRefreshAfterXLoops = 10
			} else {
				this.forceFullRoomListRefreshAfterXLoops--
			}

			/**
			 * Fetches the conversations from the server and then adds them one by one
			 * to the store.
			 */
			try {
				const response = await this.$store.dispatch('fetchConversations', {
					modifiedSince: this.roomListModifiedBefore,
				})

				// We can only support this with the HPB as otherwise rooms,
				// you are not currently active in, will not be removed anymore,
				// as there is no signaling message about it when the internal
				// signaling is used.
				if (loadState('spreed', 'signaling_mode') !== 'internal') {
					if (response?.headers && response.headers['x-nextcloud-talk-modified-before']) {
						this.roomListModifiedBefore = response.headers['x-nextcloud-talk-modified-before']
					}
				}

				this.initialisedConversations = true
				/**
				 * Emits a global event that is used in App.vue to update the page title once the
				 * ( if the current route is a conversation and once the conversations are received)
				 */
				EventBus.$emit('conversations-received', {
					singleConversation: false,
				})
				this.isFetchingConversations = false
			} catch (error) {
				console.debug('Error while fetching conversations: ', error)
				this.isFetchingConversations = false
			}
		},

		// Checks whether the conversations list is scrolled all the way to the top
		// or not
		handleScroll() {
			this.isScrolledToTop = this.$refs.container.scrollTop === 0
		},
		elementIsAboveViewpoint(container, element) {
			return element.offsetTop < container.scrollTop
		},
		elementIsBelowViewpoint(container, element) {
			return element.offsetTop + element.offsetHeight > container.scrollTop + container.clientHeight
		},
		handleUnreadMention() {
			this.unreadNum = 0
			const unreadMentions = document.querySelectorAll('.unread-mention-conversation')
			unreadMentions.forEach(x => {
				if (this.elementIsBelowViewpoint(this.$refs.container, x)) {
					if (this.unreadNum === 0) {
						this.firstUnreadPos = x.offsetTop
					}
					this.unreadNum += 1
				}
			})
		},
		debounceHandleScroll: debounce(function() {
			this.handleScroll()
			this.handleUnreadMention()
		}, 50),

		scrollToConversation(token) {
			this.$nextTick(() => {
				// In Vue 2 ref on v-for is always an array and its order is not guaranteed to match the order of v-for source
				// See https://github.com/vuejs/vue/issues/4952#issuecomment-280661367
				// Fixed in Vue 3
				// Temp solution - use unique ref name for each v-for element. The value is still array but with one element
				// TODO: Vue3: remove [0] here or use object for template refs
				const conversation = this.$refs[`conversation-${token}`]?.[0].$el
				if (!conversation) {
					return
				}

				if (this.elementIsBelowViewpoint(this.$refs.container, conversation)) {
					this.$refs.container.scrollTo({
						top: conversation.offsetTop + conversation.offsetHeight * 2 - this.$refs.container.clientHeight,
						behavior: 'smooth',
					})
				} else if (this.elementIsAboveViewpoint(this.$refs.container, conversation)) {
					this.$refs.container.scrollTo({
						top: conversation.offsetTop - conversation.offsetHeight,
						behavior: 'smooth',
					})
				}
			})
		},

		onRouteChange({ from, to }) {
			if (from.name === 'conversation'
				&& to.name === 'conversation'
				&& from.params.token === to.params.token) {
				// this is triggered when the hash in the URL changes
				return
			}
			if (from.name === 'conversation') {
				this.$store.dispatch('leaveConversation', { token: from.params.token })
				if (to.name !== 'conversation') {
					this.$store.dispatch('updateToken', '')
				}
			}
			if (to.name === 'conversation') {
				this.$store.dispatch('joinConversation', { token: to.params.token })
			}
			if (this.isMobile) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},

		handleJoinedConversation({ token }) {
			this.abortSearch()
			this.scrollToConversation(token)
		},

		iconData(item) {
			if (item.source === 'users') {
				return {
					type: CONVERSATION.TYPE.ONE_TO_ONE,
					displayName: item.label,
					name: item.id,
				}
			}
			return {
				type: CONVERSATION.TYPE.GROUP,
				objectType: item.source,
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.scroller {
	padding: 0 4px 0 6px;
}

.new-conversation {
	display: flex;
	padding: 8px 0 8px 12px;
	align-items: center;

	&--scrolled-down {
		border-bottom: 1px solid var(--color-placeholder-dark);
	}
}

// Override vue overflow rules for <ul> elements within app-navigation
.left-sidebar__list {
	height: 100% !important;
	width: 100% !important;
	overflow-y: auto !important;
	overflow-x: hidden !important;
	padding: 0;
}

.unread-mention-button {
	position: absolute !important;
	left: 50%;
	transform: translateX(-50%);
	z-index: 100;
	bottom: 10px;
	white-space: nowrap;
}

.conversations-search {
	transition: all 0.15s ease;
	z-index: 1;
	// New conversation button width : 52 px
	// Filters button width : 44 px
	// Spacing : 3px + 1px
	// Total : 100 px
	width : calc(100% - 100px);
	display : flex;
	:deep(.input-field__input) {
		border-radius: var(--border-radius-pill);
	}
	&--expanded {
		width : calc(100% - 8px);
	}

}

.filters {
	position: absolute;
	right : 52px; // New conversation button's width
	top : 5px;
	display: flex;
	height: var(--default-clickable-area);
}

.actions {
	position: absolute;
	right: 5px;
	top : 5px;
}

.filter-actions__button--active {
	background-color: var(--color-primary-element-light);
	border-radius: 6px;
	:deep(.action-button__longtext){
		font-weight: bold;
	}

}

.settings-button {
	justify-content: flex-start !important;
}

:deep(.input-field__clear-button) {
	border-radius: var(--border-radius-pill) !important;
}
:deep(.app-navigation ul) {
	padding: 0 !important;
}

:deep(.app-navigation-toggle) {
	top: 8px !important;
	right: -6px !important;
}

:deep(.app-navigation__list) {
	padding: 0 !important;
}

:deep(.list-item:focus, .list-item:focus-visible) {
	z-index: 1;
	outline: 2px solid var(--color-primary-element);
}
</style>
