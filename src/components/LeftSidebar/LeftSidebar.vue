<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation ref="leftSidebar" :aria-label="t('spreed', 'Conversation list')">
		<template #search>
			<div class="new-conversation">
				<div class="conversations-search"
					:class="{'conversations-search--expanded': isFocused}">
					<SearchBox ref="searchBox"
						:value.sync="searchText"
						:is-focused.sync="isFocused"
						:list-ref="scroller"
						@input="debounceFetchSearchResults"
						@abort-search="abortSearch" />
				</div>

				<TransitionWrapper name="radial-reveal">
					<!-- Filters -->
					<NcActions v-show="searchText === ''"
						:primary="isFiltered !== null"
						class="filters"
						:class="{'hidden-visually': isFocused}">
						<template #icon>
							<FilterIcon :size="15" />
						</template>
						<NcActionButton close-after-click
							:model-value="isFiltered === 'mentions'"
							@click="handleFilter('mentions')">
							<template #icon>
								<AtIcon :size="20" />
							</template>
							{{ t('spreed', 'Filter unread mentions') }}
						</NcActionButton>

						<NcActionButton close-after-click
							:model-value="isFiltered === 'unread'"
							@click="handleFilter('unread')">
							<template #icon>
								<MessageBadge :size="20" />
							</template>
							{{ t('spreed', 'Filter unread messages') }}
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
				</TransitionWrapper>

				<!-- Actions -->
				<TransitionWrapper name="radial-reveal">
					<NcActions v-show="searchText === ''"
						class="actions"
						:class="{'hidden-visually': isFocused}">
						<template #icon>
							<ChatPlus :size="20" />
						</template>
						<NcActionButton v-if="canStartConversations"
							close-after-click
							@click="showModalNewConversation">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('spreed', 'Create a new conversation') }}
						</NcActionButton>

						<NcActionButton v-if="canNoteToSelf && !hasNoteToSelf"
							close-after-click
							@click="restoreNoteToSelfConversation">
							<template #icon>
								<Note :size="20" />
							</template>
							{{ t('spreed', 'New personal note') }}
						</NcActionButton>

						<NcActionButton close-after-click
							@click="showModalListConversations">
							<template #icon>
								<List :size="20" />
							</template>
							{{ t('spreed', 'Join open conversations') }}
						</NcActionButton>

						<NcActionButton v-if="canModerateSipDialOut"
							close-after-click
							@click="showModalCallPhoneDialog">
							<template #icon>
								<Phone :size="20" />
							</template>
							{{ t('spreed', 'Call a phone number') }}
						</NcActionButton>
					</NcActions>
				</TransitionWrapper>

				<!-- All open conversations list -->
				<OpenConversationsList ref="openConversationsList" />

				<!-- New Conversation dialog -->
				<NewConversationDialog ref="newConversationDialog" :can-moderate-sip-dial-out="canModerateSipDialOut" />

				<!-- New phone (SIP dial-out) dialog -->
				<CallPhoneDialog v-if="canModerateSipDialOut" ref="callPhoneDialog" />

				<!-- New Pending Invitations dialog -->
				<InvitationHandler v-if="pendingInvitationsCount" ref="invitationHandler" />
			</div>
			<NcAppNavigationItem v-if="pendingInvitationsCount"
				class="invitation-button"
				:name="t('spreed', 'Pending invitations')"
				@click="showInvitationHandler">
				<template #icon>
					<AccountMultiplePlus :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble type="highlighted" :count="pendingInvitationsCount" />
				</template>
			</NcAppNavigationItem>
		</template>

		<template #list>
			<!-- Conversations List -->
			<template v-if="!isSearching">
				<NcEmptyContent v-if="initialisedConversations && filteredConversationsList.length === 0"
					:name="emptyContentLabel"
					:description="emptyContentDescription">
					<template #icon>
						<AtIcon v-if="isFiltered === 'mentions'" :size="64" />
						<MessageBadge v-else-if="isFiltered === 'unread'" :size="64" />
						<IconArchive v-else-if="showArchived" :size="64" />
						<MessageOutline v-else :size="64" />
					</template>
					<template #action>
						<NcButton v-if="isFiltered" @click="handleFilter(null)">
							<template #icon>
								<FilterRemoveIcon :size="20" />
							</template>
							{{ t('spreed', 'Clear filter') }}
						</NcButton>
					</template>
				</NcEmptyContent>
				<ConversationsListVirtual v-show="filteredConversationsList.length > 0"
					ref="scroller"
					:conversations="filteredConversationsList"
					:loading="!initialisedConversations"
					class="scroller"
					@scroll.native="debounceHandleScroll" />
				<NcButton v-if="!preventFindingUnread && lastUnreadMentionBelowViewportIndex !== null"
					class="unread-mention-button"
					type="primary"
					@click="scrollBottomUnread">
					{{ t('spreed', 'Unread mentions') }}
				</NcButton>
			</template>

			<!-- Search results -->
			<ul v-else class="scroller">
				<!-- Search results: user's conversations -->
				<NcAppNavigationCaption :name="t('spreed', 'Conversations')" />
				<Conversation v-for="item of searchResultsConversationList"
					:key="`conversation_${item.id}`"
					:ref="`conversation-${item.token}`"
					:item="item"
					@click="abortSearch" />
				<Hint v-if="searchResultsConversationList.length === 0" :hint="t('spreed', 'No matches found')" />

				<!-- Create a new conversation -->
				<NcListItem v-if="canStartConversations"
					:name="searchText"
					data-nav-id="conversation_create_new"
					@click="createConversation(searchText)">
					<template #icon>
						<ChatPlus :size="AVATAR.SIZE.DEFAULT" />
					</template>
					<template #subname>
						{{ t('spreed', 'New group conversation') }}
					</template>
				</NcListItem>

				<!-- Search results: listed (open) conversations -->
				<template v-if="!listedConversationsLoading && searchResultsListedConversations.length !== 0">
					<NcAppNavigationCaption :name="t('spreed', 'Open conversations')" />
					<Conversation v-for="item of searchResultsListedConversations"
						:key="`open-conversation_${item.id}`"
						:item="item"
						is-search-result
						@click="abortSearch" />
				</template>

				<!-- Search results: users -->
				<template v-if="searchResultsUsers.length !== 0">
					<NcAppNavigationCaption :name="t('spreed', 'Users')" />
					<NcListItem v-for="item of searchResultsUsers"
						:key="`user_${item.id}`"
						:data-nav-id="`user_${item.id}`"
						:name="item.label"
						@click="createAndJoinConversation(item)">
						<template #icon>
							<AvatarWrapper v-bind="iconData(item)" />
						</template>
						<template #subname>
							{{ t('spreed', 'New private conversation') }}
						</template>
					</NcListItem>
				</template>

				<!-- Search results: new conversations -->
				<template v-if="canStartConversations">
					<!-- New conversations: Groups -->
					<template v-if="searchResultsGroups.length !== 0">
						<NcAppNavigationCaption :name="t('spreed', 'Groups')" />
						<NcListItem v-for="item of searchResultsGroups"
							:key="`group_${item.id}`"
							:data-nav-id="`group_${item.id}`"
							:name="item.label"
							@click="createAndJoinConversation(item)">
							<template #icon>
								<ConversationIcon :item="iconData(item)" />
							</template>
							<template #subname>
								{{ t('spreed', 'New group conversation') }}
							</template>
						</NcListItem>
					</template>

					<!-- New conversations: Circles -->
					<template v-if="searchResultsCircles.length !== 0">
						<NcAppNavigationCaption :name="t('spreed', 'Teams')" />
						<NcListItem v-for="item of searchResultsCircles"
							:key="`circle_${item.id}`"
							:data-nav-id="`circle_${item.id}`"
							:name="item.label"
							@click="createAndJoinConversation(item)">
							<template #icon>
								<ConversationIcon :item="iconData(item)" />
							</template>
							<template #subname>
								{{ t('spreed', 'New group conversation') }}
							</template>
						</NcListItem>
					</template>

					<!-- New conversations: Federated users -->
					<template v-if="searchResultsFederated.length !== 0">
						<NcAppNavigationCaption :name="t('spreed', 'Federated users')" />
						<NcListItem v-for="item of searchResultsFederated"
							:key="`federated_${item.id}`"
							:data-nav-id="`federated_${item.id}`"
							:name="item.label"
							@click="createAndJoinConversation(item)">
							<template #icon>
								<AvatarWrapper v-bind="iconData(item)" />
							</template>
							<template #subname>
								{{ t('spreed', 'New group conversation') }}
							</template>
						</NcListItem>
					</template>
				</template>

				<!-- Search results: no results (yet) -->
				<template v-if="sourcesWithoutResults">
					<NcAppNavigationCaption :name="sourcesWithoutResultsList" />
					<Hint :hint="t('spreed', 'No search results')" />
				</template>
				<Hint v-else-if="contactsLoading" :hint="t('spreed', 'Loading …')" />
			</ul>
		</template>

		<template #footer>
			<div class="left-sidebar__settings-button-container">
				<template v-if="!isSearching && supportsArchive">
					<NcButton v-if="showArchived"
						type="tertiary"
						wide
						@click="showArchived = false">
						<template #icon>
							<IconArrowLeft :size="20" />
						</template>
						{{ t('spreed', 'Back to conversations') }}
					</NcButton>
					<NcButton v-else-if="archivedConversationsList.length"
						type="tertiary"
						wide
						@click="showArchived = true">
						<template #icon>
							<IconArchive :size="20" />
						</template>
						{{ t('spreed', 'Archived conversations') }}
						<span v-if="showArchivedConversationsBubble" class="left-sidebar__settings-button-bubble">{{ '⬤' }}</span>
					</NcButton>
				</template>

				<NcButton type="tertiary" wide @click="showSettings">
					<template #icon>
						<Cog :size="20" />
					</template>
					{{ t('spreed', 'Talk settings') }}
				</NcButton>
			</div>
		</template>
	</NcAppNavigation>
</template>

<script>
import debounce from 'debounce'
import { ref } from 'vue'

import AccountMultiplePlus from 'vue-material-design-icons/AccountMultiplePlus.vue'
import IconArchive from 'vue-material-design-icons/Archive.vue'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import AtIcon from 'vue-material-design-icons/At.vue'
import ChatPlus from 'vue-material-design-icons/ChatPlus.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import FilterRemoveIcon from 'vue-material-design-icons/FilterRemove.vue'
import List from 'vue-material-design-icons/FormatListBulleted.vue'
import MessageBadge from 'vue-material-design-icons/MessageBadge.vue'
import MessageOutline from 'vue-material-design-icons/MessageOutline.vue'
import Note from 'vue-material-design-icons/NoteEditOutline.vue'
import Phone from 'vue-material-design-icons/Phone.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import { useIsMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'

import CallPhoneDialog from './CallPhoneDialog/CallPhoneDialog.vue'
import Conversation from './ConversationsList/Conversation.vue'
import ConversationsListVirtual from './ConversationsList/ConversationsListVirtual.vue'
import InvitationHandler from './InvitationHandler.vue'
import OpenConversationsList from './OpenConversationsList/OpenConversationsList.vue'
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'
import ConversationIcon from '../ConversationIcon.vue'
import NewConversationDialog from '../NewConversationDialog/NewConversationDialog.vue'
import Hint from '../UIShared/Hint.vue'
import SearchBox from '../UIShared/SearchBox.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

import { useArrowNavigation } from '../../composables/useArrowNavigation.js'
import { ATTENDEE, AVATAR, CONVERSATION } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import {
	createPrivateConversation,
	fetchNoteToSelfConversation,
	searchListedConversations,
} from '../../services/conversationsService.js'
import { autocompleteQuery } from '../../services/coreService.ts'
import { EventBus } from '../../services/EventBus.ts'
import { talkBroadcastChannel } from '../../services/talkBroadcastChannel.js'
import { useFederationStore } from '../../stores/federation.ts'
import { useTalkHashStore } from '../../stores/talkHash.js'
import CancelableRequest from '../../utils/cancelableRequest.js'
import { hasUnreadMentions, hasCall, filterConversation, shouldIncludeArchived } from '../../utils/conversation.js'
import { requestTabLeadership } from '../../utils/requestTabLeadership.js'

const isFederationEnabled = getTalkConfig('local', 'federation', 'enabled')
const canModerateSipDialOut = hasTalkFeature('local', 'sip-support-dialout')
	&& getTalkConfig('local', 'call', 'sip-enabled')
	&& getTalkConfig('local', 'call', 'sip-dialout-enabled')
	&& getTalkConfig('local', 'call', 'can-enable-sip')
const canNoteToSelf = hasTalkFeature('local', 'note-to-self')
const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

export default {
	name: 'LeftSidebar',

	components: {
		AvatarWrapper,
		CallPhoneDialog,
		InvitationHandler,
		NcAppNavigation,
		NcAppNavigationCaption,
		NcAppNavigationItem,
		NcButton,
		NcCounterBubble,
		Hint,
		SearchBox,
		NewConversationDialog,
		OpenConversationsList,
		Conversation,
		NcListItem,
		ConversationIcon,
		NcActions,
		NcActionButton,
		TransitionWrapper,
		ConversationsListVirtual,
		// Icons
		AccountMultiplePlus,
		AtIcon,
		MessageBadge,
		MessageOutline,
		FilterIcon,
		FilterRemoveIcon,
		IconArchive,
		IconArrowLeft,
		Phone,
		Plus,
		ChatPlus,
		Cog,
		List,
		Note,
		NcEmptyContent,
	},

	setup() {
		const leftSidebar = ref(null)
		const searchBox = ref(null)
		const scroller = ref(null)

		const showArchived = ref(false)

		const federationStore = useFederationStore()
		const talkHashStore = useTalkHashStore()
		const { initializeNavigation, resetNavigation } = useArrowNavigation(leftSidebar, searchBox)
		const isMobile = useIsMobile()

		return {
			AVATAR,
			initializeNavigation,
			resetNavigation,
			leftSidebar,
			searchBox,
			scroller,
			federationStore,
			talkHashStore,
			isMobile,
			canModerateSipDialOut,
			canNoteToSelf,
			supportsArchive,
			showArchived,
		}
	},

	data() {
		return {
			searchText: '',
			searchResults: [],
			searchResultsUsers: [],
			searchResultsGroups: [],
			searchResultsCircles: [],
			searchResultsFederated: [],
			searchResultsListedConversations: [],
			contactsLoading: false,
			listedConversationsLoading: false,
			isCirclesEnabled: loadState('spreed', 'circles_enabled'),
			canStartConversations: loadState('spreed', 'start_conversations'),
			initialisedConversations: false,
			cancelSearchPossibleConversations: () => {},
			cancelSearchListedConversations: () => {},
			debounceFetchSearchResults: () => {},
			debounceFetchConversations: () => {},
			debounceHandleScroll: () => {},
			refreshTimer: null,
			/**
			 * @type {number|null}
			 */
			lastUnreadMentionBelowViewportIndex: null,
			preventFindingUnread: false,
			roomListModifiedBefore: 0,
			forceFullRoomListRefreshAfterXLoops: 0,
			isFetchingConversations: false,
			isCurrentTabLeader: false,
			isFocused: false,
			isFiltered: null,
			isNavigating: false,
		}
	},

	computed: {
		conversationsList() {
			return this.$store.getters.conversationsList
		},

		searchResultsConversationList() {
			if (this.searchText !== '' || this.isFocused) {
				const lowerSearchText = this.searchText.toLowerCase()
				return this.conversationsList.filter(conversation =>
					conversation.displayName.toLowerCase().includes(lowerSearchText)
					|| conversation.name.toLowerCase().includes(lowerSearchText)
				)
			} else {
				return []
			}
		},

		token() {
			return this.$store.getters.getToken()
		},

		emptyContentLabel() {
			switch (this.isFiltered) {
			case 'mentions':
			case 'unread':
				return t('spreed', 'No matches found')
			default:
				return t('spreed', 'No conversations found')
			}
		},

		emptyContentDescription() {
			if (this.showArchived) {
				return t('spreed', 'You have no archived conversations.')
			}
			switch (this.isFiltered) {
			case 'mentions':
				return t('spreed', 'You have no unread mentions.')
			case 'unread':
				return t('spreed', 'You have no unread messages.')
			default:
				return ''
			}
		},

		archivedConversationsList() {
			return this.$store.getters.archivedConversationsList
		},

		showArchivedConversationsBubble() {
			return this.archivedConversationsList
				.some(conversation => hasUnreadMentions(conversation) || hasCall(conversation))
		},

		filteredConversationsList() {
			if (this.isFocused) {
				return this.conversationsList.filter((conversation) => shouldIncludeArchived(conversation, this.showArchived))
			}

			let validConversationsCount = 0
			const filteredConversations = this.conversationsList.filter((conversation) => {
				const conversationIsValid = filterConversation(conversation, this.isFiltered)
				if (conversationIsValid) {
					validConversationsCount++
				}
				return shouldIncludeArchived(conversation, this.showArchived)
					&& (conversationIsValid || hasCall(conversation) || conversation.token === this.token)
			})
			// return empty if it only includes the current conversation without any flags
			return validConversationsCount === 0 && !this.isNavigating ? [] : filteredConversations
		},

		isSearching() {
			return this.searchText !== ''
		},

		hasNoteToSelf() {
			return this.conversationsList.find(conversation => conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF)
		},

		pendingInvitationsCount() {
			return isFederationEnabled
				? this.federationStore.pendingSharesCount
				: 0
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
						? t('spreed', 'Users, groups and teams')
						: t('spreed', 'Users and groups')
				} else {
					return (hasNoResultsCircles)
						? t('spreed', 'Users and teams')
						: t('spreed', 'Users')
				}
			} else {
				if (hasNoResultsGroups) {
					return (hasNoResultsCircles)
						? t('spreed', 'Groups and teams')
						: t('spreed', 'Groups')
				} else {
					return (hasNoResultsCircles)
						? t('spreed', 'Teams')
						: t('spreed', 'Other sources')
				}
			}
		},
	},

	watch: {
		token(value) {
			if (value && this.isFiltered) {
				this.isNavigating = true
			}
		},
	},

	beforeMount() {
		// Restore last fetched conversations from browser storage,
		// before updated ones come from server
		this.restoreConversations()

		requestTabLeadership().then(() => {
			this.isCurrentTabLeader = true
			this.fetchConversations()
			// Refreshes the conversations list every 30 seconds
			this.refreshTimer = window.setInterval(() => {
				this.fetchConversations()
			}, 30000)
		})

		talkBroadcastChannel.addEventListener('message', (event) => {
			if (this.isCurrentTabLeader) {
				switch (event.data.message) {
				case 'force-fetch-all-conversations':
					if (event.data.options?.all) {
						this.roomListModifiedBefore = 0
						this.forceFullRoomListRefreshAfterXLoops = 10
					}
					this.debounceFetchConversations()
					break
				}
			} else {
				switch (event.data.message) {
				case 'update-conversations':
					this.$store.dispatch('patchConversations', {
						conversations: event.data.conversations,
						withRemoving: event.data.withRemoving,
					})
					this.federationStore.updatePendingSharesCount(event.data.invites)
					break
				case 'update-nextcloud-talk-hash':
					this.talkHashStore.setNextcloudTalkHash(event.data.hash)
					break
				}
			}
		})
	},

	mounted() {
		this.debounceFetchSearchResults = debounce(this.fetchSearchResults, 250)
		this.debounceFetchConversations = debounce(this.fetchConversations, 3000)
		this.debounceHandleScroll = debounce(this.handleScroll, 50)

		EventBus.on('should-refresh-conversations', this.handleShouldRefreshConversations)
		EventBus.once('conversations-received', this.handleConversationsReceived)
		EventBus.on('route-change', this.onRouteChange)
		// Check filter status in previous sessions and apply if it exists
		this.handleFilter(BrowserStorage.getItem('filterEnabled'))
	},

	beforeDestroy() {
		this.debounceFetchSearchResults.clear?.()
		this.debounceFetchConversations.clear?.()
		this.debounceHandleScroll.clear?.()

		EventBus.off('should-refresh-conversations', this.handleShouldRefreshConversations)
		EventBus.off('conversations-received', this.handleConversationsReceived)
		EventBus.off('route-change', this.onRouteChange)

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
		t,
		showModalNewConversation() {
			this.$refs.newConversationDialog.showModal()
		},

		showModalListConversations() {
			this.$refs.openConversationsList.showModal()
		},

		showModalCallPhoneDialog() {
			this.$refs.callPhoneDialog.showModal()
		},

		showInvitationHandler() {
			this.$refs.invitationHandler.showModal()
		},

		handleFilter(filter) {
			this.isFiltered = filter
			// Store the active filter
			if (filter) {
				BrowserStorage.setItem('filterEnabled', filter)
			} else {
				BrowserStorage.removeItem('filterEnabled')
			}
			// Clear the search input once a filter is active
			this.searchText = ''
			// Initiate the navigation status
			this.isNavigating = false
		},

		scrollBottomUnread() {
			this.preventFindingUnread = true
			this.$refs.scroller.scrollToItem(this.lastUnreadMentionBelowViewportIndex)
			setTimeout(() => {
				this.handleUnreadMention()
				this.preventFindingUnread = false
			}, 500)
		},

		async fetchPossibleConversations() {
			this.contactsLoading = true

			try {
				// FIXME: move to conversationsStore
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(autocompleteQuery)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({
					searchText: this.searchText,
					token: 'new',
					onlyUsers: !this.canStartConversations,
				})

				this.searchResults = response?.data?.ocs?.data || []
				this.searchResultsUsers = this.searchResults.filter((match) => {
					return match.source === ATTENDEE.ACTOR_TYPE.USERS
						&& match.id !== this.$store.getters.getUserId()
						&& !this.hasOneToOneConversationWith(match.id)
				})
				this.searchResultsGroups = this.searchResults.filter((match) => match.source === ATTENDEE.ACTOR_TYPE.GROUPS)
				this.searchResultsCircles = this.searchResults.filter((match) => match.source === ATTENDEE.ACTOR_TYPE.CIRCLES)
				this.searchResultsFederated = this.searchResults.filter((match) => match.source === ATTENDEE.ACTOR_TYPE.REMOTES)
					.map((item) => {
						return { ...item, source: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS }
					})
				this.contactsLoading = false
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
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error('Error searching for open conversations', exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		async fetchSearchResults() {
			if (!this.isSearching) {
				return
			}
			this.resetNavigation()
			await Promise.all([this.fetchPossibleConversations(), this.fetchListedConversations()])
			this.initializeNavigation()
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
			if (item.source === ATTENDEE.ACTOR_TYPE.USERS) {
				// Create one-to-one conversation directly
				const conversation = await this.$store.dispatch('createOneToOneConversation', item.id)
				this.abortSearch()
				this.$router.push({
					name: 'conversation',
					params: { token: conversation.token },
				}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			} else {
				// For other types, show the modal directly
				this.$refs.newConversationDialog.showModalForItem(item)
			}
		},

		switchToConversation(conversation) {
			this.$store.dispatch('addConversation', conversation)
			this.abortSearch()
			this.$router.push({
				name: 'conversation',
				params: { token: conversation.token },
			}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		async createConversation(name) {
			try {
				const response = await createPrivateConversation(name)
				const conversation = response.data.ocs.data
				this.switchToConversation(conversation)
			} catch (error) {
				console.error('Error creating new private conversation: ', error)
			}
		},

		async restoreNoteToSelfConversation() {
			const response = await fetchNoteToSelfConversation()
			const conversation = response.data.ocs.data
			this.switchToConversation(conversation)
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

		/**
		 * @param {object} [options] Options for conversation refreshing
		 * @param {string} [options.token] The conversation token that got update
		 * @param {object} [options.properties] List of changed properties
		 * @param {boolean} [options.all] Whether all conversations should be fetched
		 */
		async handleShouldRefreshConversations(options) {
			if (options?.token && options?.properties) {
				await this.$store.dispatch('setConversationProperties', {
					token: options.token,
					properties: options.properties,
				})
			}

			if (this.isCurrentTabLeader) {
				if (options?.all === true) {
					this.roomListModifiedBefore = 0
					this.forceFullRoomListRefreshAfterXLoops = 10
				}
				this.debounceFetchConversations()
			} else {
				talkBroadcastChannel.postMessage({ message: 'force-fetch-all-conversations', options })
			}
		},

		async fetchConversations() {
			if (this.isFetchingConversations) {
				return
			}

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
				EventBus.emit('conversations-received', { singleConversation: false })
				this.isFetchingConversations = false
			} catch (error) {
				console.debug('Error while fetching conversations: ', error)
				this.isFetchingConversations = false
			}
		},

		async restoreConversations() {
			try {
				await this.$store.dispatch('restoreConversations')
				this.initialisedConversations = true
				EventBus.emit('conversations-received', { singleConversation: false })
			} catch (error) {
				console.debug('Error while restoring conversations: ', error)
			}
		},

		handleConversationsReceived() {
			this.handleUnreadMention()
			if (this.$route.params.token) {
				this.showArchived = this.$store.getters.conversation(this.$route.params.token)?.isArchived ?? false
				this.scrollToConversation(this.$route.params.token)
			}
		},

		// Checks whether the conversations list is scrolled all the way to the top
		// or not
		handleScroll() {
			this.handleUnreadMention()
		},

		/**
		 * Find position of the last unread conversation below viewport
		 */
		async handleUnreadMention() {
			await this.$nextTick()

			this.lastUnreadMentionBelowViewportIndex = null
			const lastConversationInViewport = this.$refs.scroller.getLastItemInViewportIndex()
			for (let i = this.filteredConversationsList.length - 1; i > lastConversationInViewport; i--) {
				if (hasUnreadMentions(this.filteredConversationsList[i])) {
					this.lastUnreadMentionBelowViewportIndex = i
					return
				}
			}
		},

		async scrollToConversation(token) {
			await this.$nextTick()

			if (!this.$refs.scroller) {
				return
			}

			this.$refs.scroller.scrollToConversation(token)
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
				this.abortSearch()
				this.$store.dispatch('joinConversation', { token: to.params.token })
				this.showArchived = this.$store.getters.conversation(to.params.token)?.isArchived ?? false
				this.scrollToConversation(to.params.token)
			}
			if (this.isMobile) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},

		iconData(item) {
			if (item.source === ATTENDEE.ACTOR_TYPE.USERS
				|| item.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS) {
				return {
					id: item.id,
					name: item.label,
					source: item.source,
					disableMenu: true,
					token: 'new',
					showUserStatus: true,
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
.scroller {
	height: 100%;
	padding-inline: var(--default-grid-baseline);
	overflow-y: auto;
	line-height: 20px;
}

.new-conversation {
	position: relative;
	display: flex;
	margin: calc(var(--default-grid-baseline) * 2);
	align-items: center;

	.filters {
		position: absolute;
		top: 0;
		right: calc(var(--default-grid-baseline) + var(--default-clickable-area));
	}

	.actions {
		position: absolute;
		top: 0;
		right: 0;
	}
}

.invitation-button {
	padding-inline: calc(var(--default-grid-baseline) * 2);
	margin-block: var(--default-grid-baseline);

	:deep(.app-navigation-entry-link) {
		padding-left: var(--default-grid-baseline);
	}

	:deep(.app-navigation-entry-icon) {
		flex: 0 0 40px !important; // AVATAR.SIZE.DEFAULT
	}

	:deep(.app-navigation-entry__name) {
		padding-left: calc(2 * var(--default-grid-baseline));
		font-weight: 500;
	}
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
	// TODO replace with NcAppNavigationSearch
	width: calc(100% - var(--default-grid-baseline) * 2 - var(--default-clickable-area) * 2);
	display: flex;

	&--expanded {
		width: 100%;
	}

	:deep(.input-field) {
		margin-block-start: 0;
	}
}

.left-sidebar__settings-button-container {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	padding: calc(2 * var(--default-grid-baseline));
}

.left-sidebar__settings-button-bubble {
	margin-inline: var(--default-grid-baseline);
	color: var(--color-primary-element);
}

:deep(.empty-content) {
	text-align: center;
	padding: 20% 10px 0;
}

:deep(.app-navigation__list) {
	padding: 0 !important;
}

// Overwrite NcListItem styles
:deep(.list-item) {
	overflow: hidden;
	outline-offset: -2px;

	// FIXME clean up after nextcloud/vue release
	.avatardiv .avatardiv__user-status {
		right: -2px !important;
		bottom: -2px !important;
		min-height: 14px !important;
		min-width: 14px !important;
		line-height: 1 !important;
		font-size: clamp(var(--font-size-small), 85%, var(--default-font-size)) !important;
	}
}
</style>
