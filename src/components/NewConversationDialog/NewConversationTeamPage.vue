<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import type { TeamProbe } from '../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconAccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import LoadingComponent from '../LoadingComponent.vue'
import { getTeams } from '../../services/teamsService.ts'

const selectedTeam = defineModel<TeamProbe | null>('selectedTeam', { required: true })

/**
 * Single (1), personal (2), system (4), hidden (1024), backend (2048),
 * app (131072) and SCIM (262144) teams can not have a conversation,
 * same as TeamRoomSettingsService::TEAM_CFG_UNSUPPORTED
 */
const TEAM_CFG_UNSUPPORTED = 1 | 2 | 4 | 1024 | 2048 | 131072 | 262144

const teams = ref<TeamProbe[]>([])
const isLoading = ref(true)
const searchText = ref('')

const filteredTeams = computed(() => {
	const search = searchText.value.trim().toLocaleLowerCase()
	if (!search) {
		return teams.value
	}
	return teams.value.filter((team) => team.displayName.toLocaleLowerCase().includes(search))
})

onMounted(async () => {
	try {
		// Teams the user is a member of: any member can create the conversation of a team.
		// If the team has one already, the user is added to it instead.
		const response = await getTeams()
		teams.value = response.data.ocs.data
			.filter((team) => (team.config & TEAM_CFG_UNSUPPORTED) === 0)
			.sort((a, b) => a.displayName.localeCompare(b.displayName))
	} catch (error) {
		console.error('Error while loading teams', error)
	} finally {
		isLoading.value = false
	}
})
</script>

<template>
	<div class="new-conversation-team-page">
		<NcTextField
			v-model="searchText"
			:label="t('spreed', 'Search')"
			trailingButtonIcon="close"
			:showTrailingButton="searchText !== ''"
			@trailingButtonClick="searchText = ''" />

		<LoadingComponent v-if="isLoading" />

		<NcEmptyContent
			v-else-if="filteredTeams.length === 0"
			:name="searchText ? t('spreed', 'No matches found') : t('spreed', 'No results')">
			<template #icon>
				<IconAccountGroupOutline :size="64" />
			</template>
		</NcEmptyContent>

		<ul v-else class="new-conversation-team-page__list" :aria-label="t('spreed', 'Teams')">
			<NcListItem
				v-for="team in filteredTeams"
				:key="team.id"
				:name="team.displayName"
				:active="selectedTeam?.id === team.id"
				compact
				@click="selectedTeam = team">
				<template #icon>
					<IconAccountGroupOutline :size="20" />
				</template>
				<template #subname>
					{{ team.description }}
				</template>
				<template v-if="selectedTeam?.id === team.id" #indicator>
					<IconCheck :size="20" />
				</template>
			</NcListItem>
		</ul>
	</div>
</template>

<style lang="scss" scoped>
.new-conversation-team-page {
	&__list {
		margin-top: calc(var(--default-grid-baseline) * 2);
	}
}
</style>
