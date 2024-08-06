<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcChip :text="participant.label"
		:aria-label-close="removeLabel"
		@close="$emit('update', participant)">
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

<script>
import { t } from '@nextcloud/l10n'

import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.js'

export default {
	name: 'ContactSelectionBubble',

	components: {
		AvatarWrapper,
		NcChip,
	},

	props: {
		participant: {
			type: Object,
			required: true,
		},
	},

	emits: ['update'],

	setup() {
		return { AVATAR }
	},

	computed: {
		removeLabel() {
			return t('spreed', 'Remove participant {name}', { name: this.participant.label })
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
</style>
