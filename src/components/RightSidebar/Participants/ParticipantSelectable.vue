<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, inject, ref, Ref } from 'vue'

import CheckIcon from 'vue-material-design-icons/Check.vue'

import { t } from '@nextcloud/l10n'

import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'

import { ParticipantSearchResult } from '../../../types/index.ts'
import { getStatusMessage } from '../../../utils/userStatus.js'

const props = withDefaults(defineProps<{
	participant: ParticipantSearchResult,
	showUserStatus: boolean,
}>(), {
	showUserStatus: true,
})

const emit = defineEmits<{
	(event: 'click-participant', value: ParticipantSearchResult): void
}>()

// Toggles the bulk selection state of this component
const isSelectable = inject<boolean>('bulkParticipantsSelection', false)
const selectedParticipants = inject<Ref<ParticipantSearchResult[]>>('selectedParticipants', ref([]))
const isSelected = computed<boolean>(() => isSelectable && selectedParticipants.value.some(selected => {
	return selected.id === props.participant.id && selected.source === props.participant.source
}))

const participantNavigationId = computed(() => props.participant.source + '_' + props.participant.id)
const participantName = computed(() => props.participant.label)
const participantAriaLabel = computed(() => {
	return t('spreed', 'Add participant "{user}"', { user: participantName.value })
})
const participantSubname = computed(() => {
	if (props.participant.shareWithDisplayNameUnique) {
		return props.participant.shareWithDisplayNameUnique
	}
	if (props.participant.status) {
		return getStatusMessage(props.participant.status)
	}
	return props.participant.subline
})

const preloadedUserStatus = computed(() => props.participant.status || undefined)

const handleClick = () => {
	emit('click-participant', props.participant)
}
</script>

<template>
	<NcListItem :name="participantName"
		:data-nav-id="participantNavigationId"
		class="participant"
		:active="isSelected"
		:title="participantAriaLabel"
		:aria-label="participantAriaLabel"
		@click="handleClick"
		@keydown.enter="handleClick">
		<template #icon>
			<AvatarWrapper :id="participant.id"
				token="new"
				:name="participantName"
				:source="participant.source"
				disable-menu
				disable-tooltip
				:show-user-status="showUserStatus"
				:preloaded-user-status="preloadedUserStatus" />
		</template>

		<template v-if="participantSubname" #subname>
			{{ participantSubname }}
		</template>

		<template v-if="isSelected" #extra>
			<CheckIcon :size="20" />
		</template>
	</NcListItem>
</template>

<style lang="scss" scoped>
.participant {
	// Overwrite NcListItem styles to make selection color lighter
	:deep(.list-item) {
		overflow: hidden;
		outline-offset: -2px;

		.list-item__extra {
			display: flex;
			justify-content: center;
			align-items: center;
			width: var(--default-clickable-area);
			margin: 0;
		}
	}

	&.list-item__wrapper--active,
	&.list-item__wrapper.active {
		:deep(.list-item) {
			background-color: var(--color-primary-element-light);

			&:hover,
			&:focus-within,
			&:has(:focus-visible),
			&:has(:active) {
				background-color: var(--color-primary-element-light-hover);
			}

			.list-item-content__name,
			.list-item-content__subname,
			.list-item-content__details,
			.list-item-details__details {
				color: var(--color-primary-light-text) !important;
			}
		}
	}
}
</style>
