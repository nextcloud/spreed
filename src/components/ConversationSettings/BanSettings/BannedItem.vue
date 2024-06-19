<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li :key="ban.id" class="ban-item">
		<div class="ban-item__header">
			<span class="ban-item__caption">{{ ban.bannedId }}</span>
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
			<!-- eslint-disable-next-line vue/no-v-html -->
			<li v-for="(item, index) in banInfo" :key="index" v-html="item" />
		</ul>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

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
				t('spreed', '<strong>Banned by:</strong> {actor}', { actor: this.ban.actorId },
					undefined, { escape: false, sanitize: false }),
				t('spreed', '<strong>Date:</strong> {date}', { date: moment(this.ban.bannedTime * 1000).format('lll') },
					undefined, { escape: false, sanitize: false }),
				t('spreed', '<strong>Note:</strong> {note}', { note: this.ban.internalNote },
					undefined, { escape: false, sanitize: false }),
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
