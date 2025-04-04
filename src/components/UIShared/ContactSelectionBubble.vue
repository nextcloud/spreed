<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import type { Ref } from 'vue'

import { t } from '@nextcloud/l10n'

import NcChip from '@nextcloud/vue/components/NcChip'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.ts'
import type { Participant, ParticipantSearchResult } from '../../types/index.ts'

const props = defineProps<{
	participant: Participant | ParticipantSearchResult,
}>()
const emit = defineEmits(['update'])

// Defines list of locked participants (can not be removed manually
const lockedParticipants = inject<Ref<(Participant | ParticipantSearchResult)[]>>('lockedParticipants', ref([]))

const isLocked = computed(() => lockedParticipants.value.some(item => {
	if ('actorId' in props.participant) {
		return ('actorId' in item)
			? item.actorId === props.participant.actorId && item.actorType === props.participant.actorType
			: item.id === props.participant.actorId && item.source === props.participant.actorType
	} else {
		return ('actorId' in item)
			? item.actorId === props.participant.id && item.actorType === props.participant.source
			: item.id === props.participant.id && item.source === props.participant.source
	}
}))

const actorId = computed(() => {
	return ('actorId' in props.participant) ? props.participant.actorId : props.participant.id
})
const actorType = computed(() => {
	return ('actorId' in props.participant) ? props.participant.actorType : props.participant.source
})
const computedName = computed(() => {
	return (('actorId' in props.participant) ? props.participant.displayName : props.participant.label) || t('spreed', 'Guest')
})
const token = computed(() => {
	return ('actorId' in props.participant) ? props.participant.roomToken : 'new'
})
const removeLabel = computed(() => t('spreed', 'Remove participant {name}', { name: computedName.value }))
</script>

<template>
	<NcChip :text="computedName"
		:aria-label-close="removeLabel"
		:no-close="isLocked"
		@close="emit('update', participant)">
		<template #icon>
			<AvatarWrapper :id="actorId"
				:token="token"
				:name="computedName"
				:source="actorType"
				:size="AVATAR.SIZE.EXTRA_SMALL"
				disable-menu
				disable-tooltip />
		</template>
	</NcChip>
</template>
