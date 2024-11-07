<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="wrapper" class="wrapper">
		<div class="search-form">
			<SearchBox v-if="canSearch"
				ref="searchBox"
				class="search-form__input"
				:value.sync="searchText"
				:is-focused.sync="isFocused"
				:placeholder-text="searchBoxPlaceholder"
				:aria-describedby="showSearchBoxDescription ? searchBoxDescriptionId : undefined"
				@input="handleInput"
				@keydown.enter="addParticipants(participantPhoneItem)"
				@abort-search="abortSearch" />
			<div v-if="showSearchBoxDescription"
				:id="searchBoxDescriptionId"
				:title="searchBoxDescription"
				class="search-form__description">
				<IconInformationOutline :size="20" />
				<span class="hidden-visually">{{ searchBoxDescription }}</span>
			</div>
			<DialpadPanel v-if="canAddPhones"
				:value.sync="searchText"
				@submit="addParticipants(participantPhoneItem)" />
		</div>

		<SelectPhoneNumber v-if="canAddPhones"
			:name="t('spreed', 'Add a phone number')"
			:value="searchText"
			:participant-phone-item.sync="participantPhoneItem"
			@select="addParticipants" />

		<ParticipantsListVirtual v-if="!isSearching"
			class="h-100"
			:participants="participants"
			:loading="!participantsInitialised" />

		<div v-else class="scroller h-100">
			<NcAppNavigationCaption v-if="canAdd" :name="t('spreed', 'Participants')" />

			<ParticipantsList v-if="filteredParticipants.length"
				class="known-results"
				:items="filteredParticipants"
				:loading="!participantsInitialised" />
			<Hint v-else :hint="t('spreed', 'No search results')" />

			<ParticipantsSearchResults v-if="canAdd"
				class="search-results"
				:search-results="searchResults"
				:contacts-loading="contactsLoading"
				:no-results="noResults"
				:search-text="searchText"
				@click="addParticipants" />
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'
import { ref, toRefs } from 'vue'

import IconInformationOutline from 'vue-material-design-icons/InformationOutline.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'

import ParticipantsList from './ParticipantsList.vue'
import ParticipantsListVirtual from './ParticipantsListVirtual.vue'
import ParticipantsSearchResults from './ParticipantsSearchResults.vue'
import SelectPhoneNumber from '../../SelectPhoneNumber.vue'
import DialpadPanel from '../../UIShared/DialpadPanel.vue'
import Hint from '../../UIShared/Hint.vue'
import SearchBox from '../../UIShared/SearchBox.vue'

import { useArrowNavigation } from '../../../composables/useArrowNavigation.js'
import { useGetParticipants } from '../../../composables/useGetParticipants.js'
import { useId } from '../../../composables/useId.ts'
import { useIsInCall } from '../../../composables/useIsInCall.js'
import { useSortParticipants } from '../../../composables/useSortParticipants.js'
import { ATTENDEE } from '../../../constants.js'
import { getTalkConfig, hasTalkFeature } from '../../../services/CapabilitiesManager.ts'
import { autocompleteQuery } from '../../../services/coreService.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { addParticipant } from '../../../services/participantsService.js'
import { useSidebarStore } from '../../../stores/sidebar.js'
import CancelableRequest from '../../../utils/cancelableRequest.js'

const isFederationEnabled = getTalkConfig('local', 'federation', 'enabled')

export default {
	name: 'ParticipantsTab',
	components: {
		DialpadPanel,
		Hint,
		NcAppNavigationCaption,
		ParticipantsList,
		ParticipantsListVirtual,
		ParticipantsSearchResults,
		SearchBox,
		SelectPhoneNumber,
		IconInformationOutline,
	},

	props: {
		isActive: {
			type: Boolean,
			required: true,
		},
		canSearch: {
			type: Boolean,
			required: true,
		},
		canAdd: {
			type: Boolean,
			required: true,
		},
	},

	setup(props) {
		const wrapper = ref(null)
		const searchBox = ref(null)
		const { isActive } = toRefs(props)
		const { sortParticipants } = useSortParticipants()
		const isInCall = useIsInCall()
		const { cancelableGetParticipants } = useGetParticipants(isActive, false)

		const { initializeNavigation, resetNavigation } = useArrowNavigation(wrapper, searchBox)

		const searchBoxDescriptionId = `search-box-description-${useId()}`
		const searchBoxDescription = t('spreed', 'You can search or add participants via name, email, or Federated Cloud ID')

		return {
			searchBoxDescriptionId,
			searchBoxDescription,
			initializeNavigation,
			resetNavigation,
			wrapper,
			searchBox,
			sortParticipants,
			isInCall,
			cancelableGetParticipants,
			sidebarStore: useSidebarStore(),
		}
	},

	data() {
		return {
			searchText: '',
			isFocused: false,
			searchResults: [],
			contactsLoading: false,
			participantPhoneItem: {},
			cancelSearchPossibleConversations: () => {},
			debounceFetchSearchResults: () => {},
		}
	},

	computed: {
		participantsInitialised() {
			return this.$store.getters.participantsInitialised(this.token)
		},

		participants() {
			return this.$store.getters.participantsList(this.token).slice().sort(this.sortParticipants)
		},

		filteredParticipants() {
			const isMatch = (string) => string.toLowerCase().includes(this.searchText.toLowerCase())

			return this.participants.filter(participant => {
				return isMatch(participant.displayName)
					|| (participant.actorType !== ATTENDEE.ACTOR_TYPE.GUESTS && isMatch(participant.actorId))
			})
		},

		searchBoxPlaceholder() {
			return this.canAdd
				? t('spreed', 'Search or add participants')
				: t('spreed', 'Search participants')
		},

		showSearchBoxDescription() {
			return isFederationEnabled && this.canAdd
		},

		show() {
			return this.sidebarStore.show
		},
		opened() {
			return !!this.token && this.show
		},
		token() {
			return this.$store.getters.getToken()
		},
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},
		userId() {
			return this.$store.getters.getUserId()
		},
		canAddPhones() {
			const canModerateSipDialOut = hasTalkFeature(this.token, 'sip-support-dialout')
					&& getTalkConfig(this.token, 'call', 'sip-enabled')
					&& getTalkConfig(this.token, 'call', 'sip-dialout-enabled')
					&& getTalkConfig(this.token, 'call', 'can-enable-sip')
			return canModerateSipDialOut && this.conversation.canEnableSIP
		},
		isSearching() {
			return this.searchText !== ''
		},
		noResults() {
			return this.searchResults.length === 0
		},
	},

	watch: {
		searchText(value) {
			this.isFocused = !!value
		},
	},

	beforeMount() {
		this.debounceFetchSearchResults = debounce(this.fetchSearchResults, 250)

		EventBus.on('route-change', this.abortSearch)
		EventBus.on('signaling-users-changed', this.updateUsers)
		subscribe('user_status:status.updated', this.updateUserStatus)
	},

	beforeDestroy() {
		this.debounceFetchSearchResults.clear?.()

		EventBus.off('route-change', this.abortSearch)
		EventBus.off('signaling-users-changed', this.updateUsers)
		unsubscribe('user_status:status.updated', this.updateUserStatus)

		this.cancelSearchPossibleConversations()
		this.cancelSearchPossibleConversations = null
	},

	methods: {
		t,
		async updateUsers(usersList) {
			const currentUser = usersList.flat().find(user => user.userId === this.userId)
			const currentParticipant = this.participants.find(user => user.userId === this.userId)
			if (!currentUser) {
				return
			}
			// refresh conversation, if current user permissions have been changed
			if (currentUser.participantPermissions !== this.conversation.permissions) {
				await this.$store.dispatch('fetchConversation', { token: this.token })
			}
			if (currentUser.participantPermissions !== currentParticipant?.permissions) {
				await this.cancelableGetParticipants()
			}
		},

		handleClose() {
			this.$store.dispatch('hideSidebar')
		},

		handleInput() {
			if (!this.canAdd) {
				return
			}
			this.contactsLoading = true
			this.searchResults = []
			this.debounceFetchSearchResults()
		},

		async fetchSearchResults() {
			if (!this.isSearching) {
				return
			}
			this.resetNavigation()
			try {
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(autocompleteQuery)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({
					searchText: this.searchText,
					token: this.token,
				})

				this.searchResults = response?.data?.ocs?.data || []
				this.contactsLoading = false
				this.$nextTick(() => {
					this.initializeNavigation()
				})
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error(exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		/**
		 * Add the selected group/user/circle to the conversation
		 *
		 * @param {object} item The autocomplete suggestion to start a conversation with
		 * @param {string} item.id The ID of the target
		 * @param {string} item.source The source of the target
		 */
		async addParticipants(item) {
			try {
				await addParticipant(this.token, item.id, item.source)
				if (item.source === ATTENDEE.ACTOR_TYPE.EMAILS) {
					showSuccess(t('spreed', 'Invitation was sent to {actorId}', { actorId: item.id }))
				}
				this.abortSearch()
				this.cancelableGetParticipants()
			} catch (exception) {
				console.debug(exception)
				showError(t('spreed', 'An error occurred while adding the participants'))
			}
		},

		// Ends the search operation
		abortSearch() {
			this.searchText = ''
			if (this.cancelSearchPossibleConversations) {
				this.cancelSearchPossibleConversations()
			}
		},

		updateUserStatus(state) {
			if (!this.token) {
				return
			}

			const participant = this.participants.find(participant => participant.actorId === state.userId)
			if (participant && (participant.status !== state.status
				|| participant.statusMessage !== state.message
				|| participant.statusIcon !== state.icon
				|| participant.statusClearAt !== state.clearAt
			)) {
				this.$store.dispatch('updateUser', {
					token: this.token,
					participantIdentifier: {
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
						actorId: state.userId,
					},
					updatedData: {
						status: state.status,
						statusIcon: state.icon,
						statusMessage: state.message,
						statusClearAt: state.clearAt,
					},
				})
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.wrapper {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.h-100 {
	height: 100%;
}

.search-form {
	display: flex;
	align-items: center;
	gap: 4px;
	padding-inline: var(--default-grid-baseline);

	.search-form__input {
		margin: 0;
	}

	&__description {
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		display: flex;
		align-items: center;
		justify-content: center;

		&,
		& > :deep(*) {
			cursor: help;
		}
	}
}

.scroller {
	overflow-y: auto;
}

.known-results {
	padding: 0 2px;
}

.search-results {
	margin-top: 12px; // compensate margin before first header inside
}

/** TODO: fix these in the nextcloud-vue library **/

:deep(.app-sidebar-header__menu) {
	top: 6px !important;
	margin-top: 0 !important;
	right: 54px !important;
}

:deep(.app-sidebar__close) {
	top: 6px !important;
	right: 6px !important;
}

:deep(.app-navigation-caption):not(:first-child) {
	margin-top: 12px !important;
}

:deep(.app-navigation-caption__name) {
	margin: 0 !important;
}

/*
 * The field will fully overlap the top of the sidebar content so
 * that elements will scroll behind it
 */
.app-navigation-search {
	top: -10px;
	margin: -10px;
	padding: 10px;
}

</style>
