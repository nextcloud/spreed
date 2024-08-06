<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup>
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.js'

const props = defineProps({
	participant: {
		type: Object,
		required: true,
	},
})
const emit = defineEmits(['update'])

const removeLabel = computed(() => t('spreed', 'Remove participant {name}', { name: props.participant.label }))
</script>

<template>
	<NcChip :text="participant.label"
		:aria-label-close="removeLabel"
		@close="emit('update', participant)">
		<template #icon>
			<AvatarWrapper :id="participant.id"
				token="new"
				:name="participant.label"
				:source="participant.source"
				:size="AVATAR.SIZE.EXTRA_SMALL"
				disable-menu
				disable-tooltip />
		</template>
	</NcChip>
</template>

<style lang="scss" scoped>
</style>
