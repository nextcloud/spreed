<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="wrapper" class="set-contacts">
		<!-- Search -->
		<div class="set-contacts__form">
			<NcTextField ref="setContacts"
				v-model="searchText"
				v-intersection-observer="visibilityChanged"
				type="text"
				class="set-contacts__form-input"
				:label="textFieldLabel"
				:show-trailing-button="isSearching"
				:trailing-button-label="cancelSearchLabel"
				@trailing-button-click="abortSearch"
				@input="handleInput">
				<template #icon>
					<Magnify :size="16" />
				</template>
				<template #trailing-button-icon>
					<Close :size="20" />
				</template>
			</NcTextField>
			<DialpadPanel v-if="canModerateSipDialOut"
				container=".set-contacts__form"
				:value.sync="searchText"
				@submit="addParticipantPhone" />
		</div>

		<!-- Selected results -->
		<TransitionWrapper v-if="hasSelectedParticipants"
			class="selected-participants"
			name="zoom"
			tag="div"
			group>
			<ContactSelectionBubble v-for="participant in selectedParticipants"
				:key="participant.source + participant.id"
				:participant="participant"
				@update="updateSelectedParticipants" />
		</TransitionWrapper>

		<!-- Search results -->
		<SelectPhoneNumber v-if="canModerateSipDialOut"
			:name="t('spreed', 'Add a phone number')"
			:value="searchText"
			:participant-phone-item.sync="participantPhoneItem"
			@select="addParticipantPhone" />
		<ParticipantsSearchResults :search-results="searchResults"
			:contacts-loading="contactsLoading"
			:no-results="noResults"
			scrollable
			:show-search-hints="!onlyUsers"
			:token="token"
			:only-users="onlyUsers"
			@click="updateSelectedParticipants"
			@click-search-hint="focusInput" />
	</div>
</template>

<script>
import { vIntersectionObserver as IntersectionObserver } from '@vueuse/components'
import debounce from 'debounce'
import { ref } from 'vue'

import Close from 'vue-material-design-icons/Close.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcTextField from '@nextcloud/vue/components/NcTextField'

import ParticipantsSearchResults from '../RightSidebar/Participants/ParticipantsSearchResults.vue'
import SelectPhoneNumber from '../SelectPhoneNumber.vue'
import ContactSelectionBubble from '../UIShared/ContactSelectionBubble.vue'
import DialpadPanel from '../UIShared/DialpadPanel.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

import { useArrowNavigation } from '../../composables/useArrowNavigation.js'
import { SHARE } from '../../constants.ts'
import { autocompleteQuery } from '../../services/coreService.ts'
import CancelableRequest from '../../utils/cancelableRequest.js'

export default {
	name: 'NewConversationContactsPage',

	components: {
		ContactSelectionBubble,
		DialpadPanel,
		NcTextField,
		ParticipantsSearchResults,
		SelectPhoneNumber,
		TransitionWrapper,
		// Icons
		Close,
		Magnify,
	},

	directives: {
		IntersectionObserver,
	},

	props: {
		token: {
			type: String,
			default: '',
		},

		selectedParticipants: {
			type: Array,
			required: true,
		},

		canModerateSipDialOut: {
			type: Boolean,
			default: false,
		},

		onlyUsers: {
			type: Boolean,
			required: false,
		},
	},

	emits: ['update:selected-participants'],

	setup() {
		const wrapper = ref(null)
		const setContacts = ref(null)

		const { initializeNavigation, resetNavigation } = useArrowNavigation(wrapper, setContacts)

		return {
			initializeNavigation,
			resetNavigation,
			wrapper,
			setContacts,
		}
	},

	data() {
		return {
			searchText: '',
			searchResults: [],
			cachedFullSearchResults: [],
			// The loading state is true when the component is initialised as we perform a search for 'contacts'
			// with an empty screen as search text.
			contactsLoading: true,
			noResults: false,
			participantPhoneItem: {},
			cancelSearchPossibleConversations: () => {},
			debounceFetchSearchResults: () => {},
		}
	},

	computed: {
		hasSelectedParticipants() {
			return this.selectedParticipants.length !== 0
		},

		isSearching() {
			return this.searchText !== ''
		},

		textFieldLabel() {
			return this.canModerateSipDialOut
				? t('spreed', 'Search participants or phone numbers')
				: t('spreed', 'Search participants')
		},

		cancelSearchLabel() {
			return t('spreed', 'Cancel search')
		},
	},

	mounted() {
		this.debounceFetchSearchResults = debounce(this.fetchSearchResults, 250)

		this.$nextTick(() => {
			// Focus the input field of the current component.
			this.focusInput()
			// Perform a search with an empty string
			this.fetchSearchResults()
		})
	},

	beforeDestroy() {
		this.debounceFetchSearchResults.clear?.()

		this.cancelSearchPossibleConversations()
		this.cancelSearchPossibleConversations = null
	},

	methods: {
		t,
		handleInput() {
			this.noResults = false
			this.contactsLoading = true
			this.searchResults = []
			this.debounceFetchSearchResults()
		},

		abortSearch() {
			this.noResults = false
			this.contactsLoading = false
			this.searchResults = this.cachedFullSearchResults
			this.searchText = ''
			this.participantPhoneItem = {}
			this.focusInput()
		},

		async fetchSearchResults() {
			this.resetNavigation()
			this.contactsLoading = true
			try {
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(autocompleteQuery)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({
					searchText: this.searchText,
					token: this.token || 'new',
					forceTypes: [SHARE.TYPE.EMAIL], // email guests are allowed directly after conversation creation
				})

				this.searchResults = response?.data?.ocs?.data || []
				if (this.searchResults.length === 0) {
					this.noResults = true
				}
				if (!this.searchText) {
					this.cachedFullSearchResults = this.searchResults
				}
				this.$nextTick(() => {
					this.initializeNavigation()
				})
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error(exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			} finally {
				this.contactsLoading = false
			}
		},

		visibilityChanged([{ isIntersecting }]) {
			if (isIntersecting) {
				// Focus the input field of the current component.
				this.focusInput()
			}
		},

		focusInput() {
			this.setContacts.focus()
		},

		updateSelectedParticipants(participant) {
			const isSelected = this.selectedParticipants.some(selected => {
				return selected.id === participant.id && selected.source === participant.source
			})
			const payload = isSelected
				? this.selectedParticipants.filter(selected => {
						return selected.id !== participant.id || selected.source !== participant.source
					})
				: [...this.selectedParticipants, participant]

			this.$emit('update:selected-participants', payload)
		},

		addParticipantPhone() {
			if (!this.participantPhoneItem?.phoneNumber) {
				return
			}

			this.updateSelectedParticipants(this.participantPhoneItem)
		}
	},
}
</script>

<style lang="scss" scoped>
.set-contacts {
	height: 100%;
	&__icon {
		margin-top: 40px;
	}
	&__hint {
		margin-top: 20px;
		text-align: center;
	}

	&__form {
		display: flex;
		align-items: flex-end;
		gap: var(--default-grid-baseline);

		&-input {
			margin: 0;
		}
	}
}

.selected-participants {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline);
	border-bottom: 1px solid var(--color-background-darker);
	padding: var(--default-grid-baseline) 0;
	min-height: min-content;
	max-height: 97px;
	overflow-y: auto;
	align-content: flex-start;
}
</style>
