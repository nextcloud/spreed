<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li :key="ban.id" class="ban-item">
		<div class="ban-item__header">
			<span class="ban-item__caption">{{ ban.bannedDisplayName }}</span>
			<div class="ban-item__buttons">
				<NcButton type="tertiary" @click="showDetails = !showDetails">
					{{ showDetails ? t('spreed', 'Hide details') : t('spreed', 'Show details') }}
				</NcButton>
				<NcButton @click="$emit('unban-participant')">
					{{ t('spreed', 'Unban') }}
				</NcButton>
			</div>
		</div>
		<ul v-if="showDetails" class="ban-item__hint">
			<li v-for="(item, index) in banInfo" :key="index">
				<strong>{{ item.label }}</strong>
				<span>{{ item.value }}</span>
			</li>
		</ul>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

import { formatDateTime } from '../../../utils/formattedTime.ts'

export default {
	name: 'BannedItem',

	components: {
		NcButton,
	},

	props: {
		ban: {
			type: Object,
			required: true,
		},
	},

	emits: ['unban-participant'],

	data() {
		return {
			showDetails: false,
		}
	},

	computed: {
		banInfo() {
			return [
				// TRANSLATORS name of a moderator who banned a participant
				{ label: t('spreed', 'Banned by:'), value: this.ban.moderatorDisplayName },
				// TRANSLATORS Date and time of ban creation
				{ label: t('spreed', 'Date:'), value: formatDateTime(this.ban.bannedTime * 1000, 'lll') },
				// TRANSLATORS Internal note for moderators, usually a reason for this ban
				{ label: t('spreed', 'Note:'), value: this.ban.internalNote },
			]
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>

.ban-item {
	padding: 4px 0;
	&:not(:last-child) {
		border-bottom: 1px solid var(--color-border);
	}

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	&__caption {
		font-weight: bold;
	}

	&__hint {
		word-wrap: break-word;
		color: var(--color-text-maxcontrast);
		margin-bottom: 4px;
	}

	&__buttons {
		display: flex;
	}
}
</style>
