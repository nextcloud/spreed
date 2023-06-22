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
	<div class="set-contacts">
		<!-- Search -->
		<NcTextField ref="setContacts"
			v-observe-visibility="visibilityChanged"
			:value.sync="searchText"
			type="text"
			:label="t('spreed', 'Search participants')"
			:show-trailing-button="isSearching"
			:trailing-button-label="cancelSearchLabel"
			@trailing-button-click="abortSearch"
			@input="handleInput">
			<Magnify :size="16" />
			<template #trailing-button-icon>
				<Close :size="20" />
			</template>
		</NcTextField>
		<transition-group v-if="hasSelectedParticipants"
			name="zoom"
			tag="div"
			class="selected-participants">
			<ContactSelectionBubble v-for="participant in selectedParticipants"
				:key="participant.source + participant.id"
				:participant="participant" />
		</transition-group>
		<ParticipantSearchResults :add-on-click="false"
			:search-results="searchResults"
			:contacts-loading="contactsLoading"
			:no-results="noResults"
			:scrollable="true"
			:display-search-hint="displaySearchHint"
			:selectable="true"
			@click-search-hint="focusInput" />
	</div>
</template>

<script>
import debounce from 'debounce'

import Close from 'vue-material-design-icons/Close.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'

import { showError } from '@nextcloud/dialogs'

import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import ParticipantSearchResults from '../../../RightSidebar/Participants/ParticipantsSearchResults/ParticipantsSearchResults.vue'
import ContactSelectionBubble from './ContactSelectionBubble/ContactSelectionBubble.vue'

import { searchPossibleConversations } from '../../../../services/conversationsService.js'
import CancelableRequest from '../../../../utils/cancelableRequest.js'

export default {
	name: 'SetContacts',
	components: {
		Close,
		ParticipantSearchResults,
		ContactSelectionBubble,
		NcTextField,
		Magnify,
	},

	props: {
		conversationName: {
			type: String,
			required: true,
		},
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
			cancelSearchPossibleConversations: () => {},
		}
	},

	computed: {
		selectedParticipants() {
			return this.$store.getters.selectedParticipants
		},
		hasSelectedParticipants() {
			return this.selectedParticipants.length !== 0
		},
		/**
		 * Search hint at the bottom of the participants list, displayed only if
		 * the user is not searching
		 *
		 * @return {boolean}
		 */
		displaySearchHint() {
			return !this.contactsLoading && this.searchText === ''
		},

		isSearching() {
			return this.searchText !== ''
		},

		cancelSearchLabel() {
			return t('spreed', 'Cancel search')
		},
	},

	async mounted() {
		// Focus the input field of the current component.
		this.focusInput()
		// Perform a search with an empty string
		await this.fetchSearchResults()
	},

	beforeDestroy() {
		this.cancelSearchPossibleConversations()
		this.cancelSearchPossibleConversations = null
	},

	methods: {
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
			this.focusInput()
		},

		debounceFetchSearchResults: debounce(function() {
			this.fetchSearchResults()
		}, 250),

		async fetchSearchResults() {
			this.contactsLoading = true
			try {
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(searchPossibleConversations)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({ searchText: this.searchText })

				this.searchResults = response?.data?.ocs?.data || []
				if (this.searchResults.length === 0) {
					this.noResults = true
				}
				if (!this.searchText) {
					this.cachedFullSearchResults = this.searchResults
				}
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
		visibilityChanged(isVisible) {
			if (isVisible) {
				// Focus the input field of the current component.
				this.focusInput()
			}
		},
		focusInput() {
			this.$refs.setContacts.$el.focus()
		},
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
}

.selected-participants {
	display: flex;
	flex-wrap: wrap;
	border-bottom: 1px solid var(--color-background-darker);
	padding: 4px 0;
	max-height: 97px;
	overflow-y: auto;
	flex: 0 240px;
	flex: 1 0 auto;
	align-content: flex-start;
}

.zoom-enter-active {
	animation: zoom-in var(--animation-quick);
}

.zoom-leave-active {
	animation: zoom-in var(--animation-quick) reverse;
	will-change: transform;
}

@keyframes zoom-in {
	0% {
		transform: scale(0);
	}
	100% {
		transform: scale(1);
	}
}

</style>
