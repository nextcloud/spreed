<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="participants-search-results" :class="{'scrollable': scrollable }">
		<template v-if="addableUsers.length !== 0">
			<NcAppNavigationCaption :name="t('spreed', 'Add users')" />
			<ParticipantsList :items="addableUsers"
				@click="handleClickParticipant" />
		</template>

		<template v-if="addableGroups.length !== 0">
			<NcAppNavigationCaption :name="t('spreed', 'Add groups')" />
			<ParticipantsList :items="addableGroups"
				@click="handleClickParticipant" />
		</template>

		<template v-if="addableEmails.length !== 0">
			<NcAppNavigationCaption :name="t('spreed', 'Add emails')" />
			<ParticipantsList :items="addableEmails"
				@click="handleClickParticipant" />
		</template>

		<template v-if="addableCircles.length !== 0">
			<NcAppNavigationCaption :name="t('spreed', 'Add circles')" />
			<ParticipantsList :items="addableCircles"
				@click="handleClickParticipant" />
		</template>

		<!-- integrations -->
		<template v-if="integrations.length !== 0">
			<NcAppNavigationCaption :name="t('spreed', 'Integrations')" />
			<ul>
				<NcButton v-for="(integration, index) in integrations"
					:key="'integration' + index"
					type="tertiary-no-background"
					@click="runIntegration(integration)">
					<!-- FIXME: dynamically change the material design icon -->
					<template #icon>
						<AccountPlus :size="20" />
					</template>
					{{ integration.label }}
				</NcButton>
			</ul>
		</template>

		<template v-if="addableRemotes.length !== 0">
			<NcAppNavigationCaption :name="t('spreed', 'Add federated users')" />
			<ParticipantsList :items="addableRemotes"
				@click="handleClickParticipant" />
		</template>

		<NcAppNavigationCaption v-if="sourcesWithoutResults" :name="sourcesWithoutResultsList" />
		<Hint v-if="contactsLoading" :hint="t('spreed', 'Searching …')" />
		<Hint v-else-if="sourcesWithoutResults" :hint="t('spreed', 'No search results')" />

		<template v-if="showSearchHints">
			<NcEmptyContent v-if="noResults" :name="t('spreed', 'No results')">
				<template #icon>
					<AccountSearch />
				</template>
			</NcEmptyContent>
			<NcButton v-else-if="displaySearchHint"
				class="participants-search-results__hint"
				type="tertiary"
				@click="handleClickHint">
				<template #icon>
					<AccountSearch :size="20" />
				</template>
				{{ t('spreed', 'Search for more users') }}
			</NcButton>
		</template>
	</div>
</template>

<script>
import AccountPlus from 'vue-material-design-icons/AccountPlus.vue'
import AccountSearch from 'vue-material-design-icons/AccountSearch.vue'

import { loadState } from '@nextcloud/initial-state'

import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import Hint from '../../../Hint.vue'
import ParticipantsList from '../ParticipantsList/ParticipantsList.vue'

import { useIntegrationsStore } from '../../../../stores/integrations.js'

const isCirclesEnabled = loadState('spreed', 'circles_enabled')

export default {
	name: 'ParticipantsSearchResults',

	components: {
		AccountPlus,
		AccountSearch,
		Hint,
		NcAppNavigationCaption,
		NcButton,
		NcEmptyContent,
		ParticipantsList,
	},

	props: {
		searchResults: {
			type: Array,
			required: true,
		},
		contactsLoading: {
			type: Boolean,
			required: true,
		},
		/**
		 * Display no-results state instead of list.
		 */
		noResults: {
			type: Boolean,
			default: false,
		},
		/**
		 * Display loading state instead of list.
		 */
		loading: {
			type: Boolean,
			default: false,
		},
		scrollable: {
			type: Boolean,
			default: false,
		},
		showSearchHints: {
			type: Boolean,
			default: false,
		},
		searchText: {
			type: String,
			default: '',
		},
	},

	emits: ['click', 'click-search-hint'],

	setup() {
		const { participantSearchActions } = useIntegrationsStore()

		return {
			isCirclesEnabled,
			participantSearchActions,
		}
	},

	computed: {
		circlesWithoutResults() {
			return this.isCirclesEnabled && !this.addableCircles.length
		},

		sourcesWithoutResults() {
			return !this.addableUsers.length || !this.addableGroups.length || this.circlesWithoutResults
		},

		integrations() {
			return this.participantSearchActions.filter((integration) => integration.show(this.searchText))
		},

		sourcesWithoutResultsList() {
			if (!this.addableUsers.length) {
				if (!this.addableGroups.length) {
					return this.circlesWithoutResults
						? t('spreed', 'Add users, groups or circles')
						: t('spreed', 'Add users or groups')
				} else {
					return this.circlesWithoutResults
						? t('spreed', 'Add users or circles')
						: t('spreed', 'Add users')
				}
			} else {
				if (!this.addableGroups.length) {
					return this.circlesWithoutResults
						? t('spreed', 'Add groups or circles')
						: t('spreed', 'Add groups')
				} else {
					return this.circlesWithoutResults
						? t('spreed', 'Add circles')
						: t('spreed', 'Add other sources')
				}
			}
		},

		participants() {
			return this.$store.getters.participantsList(this.token)
		},

		addableUsers() {
			return this.searchResults.filter(item => item.source === 'users')
				.filter(user => !this.participants.some(participant => user.id === participant.userId))
		},
		addableGroups() {
			return this.searchResults.filter((item) => item.source === 'groups')
		},
		addableEmails() {
			return this.searchResults.filter((item) => item.source === 'emails')
		},
		addableCircles() {
			return this.searchResults.filter((item) => item.source === 'circles')
		},
		addableRemotes() {
			return this.searchResults.filter((item) => item.source === 'remotes')
				// TODO remove when Federation feature is ready
				.concat(OC.debug
					? this.addableUsers.map(user => ({
						...user,
						id: user.id + '@' + window.location.host,
						label: user.id + '@' + window.location.host,
						source: 'remotes',
					}))
					: [])
		},

		displaySearchHint() {
			return !this.contactsLoading && this.searchText === ''
		},
	},
	methods: {
		handleClickParticipant(participant) {
			this.$emit('click', participant)
		},

		handleClickHint() {
			this.$emit('click-search-hint')
		},

		runIntegration(integration) {
			integration.callback(this.searchText).then((participant) => {
				this.$emit('click', participant)
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.scrollable {
	overflow-y: auto;
	overflow-x: hidden;
	flex-shrink: 1;
}

.participants-search-results {
	padding: 0 2px;

	&__hint {
		margin: 20px auto 0;
	}
}

</style>
