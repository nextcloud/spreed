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
		<div class="icon-search" />
		<input ref="setContacts"
			v-model="searchText"
			v-observe-visibility="visibilityChanged"
			class="set-contacts__input"
			type="text"
			:placeholder="t('spreed', 'Search participants')"
			@input="handleInput">
		<NcButton v-if="isSearching"
			class="abort-search"
			type="tertiary-no-background"
			:aria-label="cancelSearchLabel"
			@click="abortSearch">
			<template #icon>
				<Close :size="20" />
			</template>
		</NcButton>
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
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Close from 'vue-material-design-icons/Close.vue'
import CancelableRequest from '../../../../utils/cancelableRequest.js'
import debounce from 'debounce'
import { showError } from '@nextcloud/dialogs'
import { searchPossibleConversations } from '../../../../services/conversationsService.js'
import ParticipantSearchResults from '../../../RightSidebar/Participants/ParticipantsSearchResults/ParticipantsSearchResults.vue'
import ContactSelectionBubble from './ContactSelectionBubble/ContactSelectionBubble.vue'

export default {
	name: 'SetContacts',
	components: {
		NcButton,
		Close,
		ParticipantSearchResults,
		ContactSelectionBubble,
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
		// Once the contacts are fetched, remove the spinner.
		this.contactsLoading = false
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
			this.searchResults = []
			this.searchText = ''
			this.focusInput()
		},

		debounceFetchSearchResults: debounce(function() {
			this.fetchSearchResults()
		}, 250),

		async fetchSearchResults() {
			try {
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(searchPossibleConversations)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({ searchText: this.searchText })

				this.searchResults = response?.data?.ocs?.data || []
				this.contactsLoading = false
				if (this.searchResults.length === 0) {
					this.noResults = true
				}
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error(exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},
		visibilityChanged(isVisible) {
			if (isVisible) {
				// Focus the input field of the current component.
				this.focusInput()
			}
		},
		focusInput() {
			this.$refs.setContacts.focus()
		},
	},
}
</script>

<style lang="scss" scoped>
.set-contacts {
	position: relative;
	height: 100%;
	display: flex;
	flex-direction: column;
	overflow: hidden;
	&__input {
		width: 100%;
		font-size: 16px;
		padding-left: 28px;
		line-height: 34px;
		box-shadow: 0 10px 5px var(--color-main-background);
		z-index: 1;
	}
	&__icon {
		margin-top: 40px;
	}
	&__hint {
		margin-top: 20px;
		text-align: center;
	}
	.abort-search {
		position: absolute;
		right: 0;
		top: -2px;
		z-index: 2;
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

.icon-search {
	position: absolute;
	top: 12px;
	left: 8px;
	z-index: 2;
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
