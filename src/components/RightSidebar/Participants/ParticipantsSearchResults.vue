<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="participants-search-results" :class="{'scrollable': scrollable }">
		<template v-if="addableUsers.length !== 0">
			<NcAppNavigationCaption v-if="!onlyUsers" :name="t('spreed', 'Add users')" />
			<ParticipantsList :items="addableUsers"
				is-search-result
				@click="handleClickParticipant" />
		</template>

		<template v-if="!onlyUsers">
			<template v-if="addableGroups.length !== 0">
				<NcAppNavigationCaption :name="t('spreed', 'Add groups')" />
				<ParticipantsList :items="addableGroups"
					is-search-result
					@click="handleClickParticipant" />
			</template>

			<template v-if="addableEmails.length !== 0">
				<NcAppNavigationCaption :name="t('spreed', 'Add emails')" />
				<ParticipantsList :items="addableEmails"
					is-search-result
					@click="handleClickParticipant" />
			</template>

			<template v-if="addableCircles.length !== 0">
				<NcAppNavigationCaption :name="t('spreed', 'Add teams')" />
				<ParticipantsList :items="addableCircles"
					is-search-result
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
					is-search-result
					@click="handleClickParticipant" />
			</template>
		</template>

		<NcAppNavigationCaption v-if="sourcesWithoutResults && !onlyUsers" :name="sourcesWithoutResultsList" />
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
import { t } from '@nextcloud/l10n'

import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import ParticipantsList from './ParticipantsList.vue'
import Hint from '../../UIShared/Hint.vue'

import { ATTENDEE } from '../../../constants.ts'
import { useIntegrationsStore } from '../../../stores/integrations.js'

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
		 * Token of current conversation (if provided).
		 */
		token: {
			type: String,
			default: '',
		},
		/**
		 * Display no-results state instead of list.
		 */
		noResults: {
			type: Boolean,
			default: false,
		},
		/**
		 * Display only results from internal users.
		 */
		onlyUsers: {
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
			if (this.onlyUsers) {
				return !this.addableUsers.length
			} else {
				return !this.addableUsers.length || !this.addableGroups.length || this.circlesWithoutResults
			}
		},

		integrations() {
			return this.participantSearchActions.filter((integration) => integration.show(this.searchText))
		},

		sourcesWithoutResultsList() {
			if (!this.addableUsers.length) {
				if (!this.addableGroups.length) {
					return this.circlesWithoutResults
						? t('spreed', 'Add users, groups or teams')
						: t('spreed', 'Add users or groups')
				} else {
					return this.circlesWithoutResults
						? t('spreed', 'Add users or teams')
						: t('spreed', 'Add users')
				}
			} else {
				if (!this.addableGroups.length) {
					return this.circlesWithoutResults
						? t('spreed', 'Add groups or teams')
						: t('spreed', 'Add groups')
				} else {
					return this.circlesWithoutResults
						? t('spreed', 'Add teams')
						: t('spreed', 'Add other sources')
				}
			}
		},

		participants() {
			return this.$store.getters.participantsList(this.token)
		},

		addableUsers() {
			return this.searchResults.filter(item => item.source === ATTENDEE.ACTOR_TYPE.USERS)
				.filter(user => !this.participants.some(participant => user.id === participant.userId))
		},
		addableGroups() {
			return this.searchResults.filter((item) => item.source === ATTENDEE.ACTOR_TYPE.GROUPS)
		},
		addableEmails() {
			return this.searchResults.filter((item) => item.source === ATTENDEE.ACTOR_TYPE.EMAILS)
		},
		addableCircles() {
			return this.searchResults.filter((item) => item.source === ATTENDEE.ACTOR_TYPE.CIRCLES)
		},
		addableRemotes() {
			return this.searchResults.filter((item) => item.source === ATTENDEE.ACTOR_TYPE.REMOTES)
				.map((item) => {
					return { ...item, source: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS }
				})
				// TODO remove when Federation feature is ready
				.concat(OC.debug
					? this.addableUsers.map(user => ({
						...user,
						id: user.id + '@' + window.location.host,
						label: user.id + '@' + window.location.host,
						source: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS,
					}))
					: [])
		},

		displaySearchHint() {
			return !this.contactsLoading && this.searchText === ''
		},
	},
	methods: {
		t,
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

	:deep(.app-navigation-hint):first-child {
		margin-top: var(--default-grid-baseline) !important;
	}
}

</style>
