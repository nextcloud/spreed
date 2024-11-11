<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcPopover class="poll-voters-details" trigger="hover">
		<template #trigger>
			<NcButton type="tertiary-no-background"
				:aria-label="t('spreed','Voted participants')"
				class="poll-voters-details__button">
				<template #icon>
					<AvatarWrapper v-for="(item, index) in details.slice(0, 8)"
						:id="item.actorId"
						:key="index"
						:token="token"
						:name="getDisplayName(item)"
						:source="item.actorType"
						:size="AVATAR.SIZE.EXTRA_SMALL"
						condensed
						disable-menu
						disable-tooltip />
				</template>
			</NcButton>
		</template>
		<div class="poll-voters-details__popover" tabindex="0">
			<div v-for="(item, index) in details"
				:key="index"
				class="poll-voters-details__list-item">
				<AvatarWrapper :id="item.actorId"
					:token="token"
					:name="item.actorDisplayName.trim()"
					:source="item.actorType"
					:size="AVATAR.SIZE.EXTRA_SMALL"
					disable-menu />
				<p class="poll-voters-details__display-name">
					{{ getDisplayName(item) }}
				</p>
			</div>
		</div>
	</NcPopover>
</template>

<script>
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.js'
import { getDisplayNameWithFallback } from '../../utils/getDisplayName.ts'

export default {

	name: 'PollVotersDetails',

	components: {
		AvatarWrapper,
		NcButton,
		NcPopover,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		details: {
			type: Array,
			required: true,
		},
	},

	setup() {
		return { AVATAR }
	},

	methods: {
		t,
		getDisplayName(item) {
			return getDisplayNameWithFallback(item.actorDisplayName, item.actorType)
		},
	},
}
</script>

<style lang="scss" scoped>

.poll-voters-details {
	max-width: 30%;
	margin-right: 8px;

	& &__button,
	&__button :deep(.button-vue__icon) {
		min-height: auto;
		height: auto;
		min-width: auto;
		width: auto !important;
		flex-wrap: wrap;
		justify-content: flex-start;
		border-radius: 0;
		overflow: visible;
	}

	&__popover {
		padding: 8px;
		max-height: 400px;
		overflow-y: scroll;
	}

	&__display-name {
			margin-left: 4px;
		}

	&__list-item {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		min-width: 150px;
		height: 32px;
		margin-bottom: var(--margin-small);
	}
}

</style>
