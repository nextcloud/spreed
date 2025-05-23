<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="conversation-ban__settings">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Banned users') }}
		</h4>
		<div class="app-settings-section__hint">
			{{ t('spreed', 'Manage the list of banned users in this conversation.') }}
		</div>
		<NcButton @click="modal = true">
			{{ t('spreed', 'Manage bans') }}
		</NcButton>

		<NcDialog :name="t('spreed', 'Banned users')"
			:open.sync="modal"
			size="normal"
			close-on-click-outside
			container=".conversation-ban__settings">
			<div class="conversation-ban__content">
				<ul v-if="banList.length" class="conversation-ban__list">
					<BannedItem v-for="ban in banList"
						:key="ban.id"
						:ban="ban"
						@unban-participant="handleUnban(ban.id)" />
				</ul>

				<NcEmptyContent v-else>
					<template #icon>
						<NcLoadingIcon v-if="isLoading" />
						<AccountCancel v-else />
					</template>

					<template #description>
						<p>{{ isLoading ? t('spreed', 'Loading …') : t('spreed', 'No banned users') }}</p>
					</template>
				</NcEmptyContent>
			</div>
		</NcDialog>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AccountCancel from 'vue-material-design-icons/AccountCancel.vue'
import BannedItem from './BannedItem.vue'
import { getConversationBans, unbanActor } from '../../../services/banService.ts'

export default {
	name: 'BanSettings',

	components: {
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		BannedItem,
		// Icons
		AccountCancel,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			banList: [],
			isLoading: true,
			modal: false,
		}
	},

	watch: {
		modal(value) {
			if (value) {
				this.getList()
			}
		},
	},

	methods: {
		t,

		async getList() {
			this.isLoading = true
			const response = await getConversationBans(this.token)
			this.banList = response.data.ocs.data
			this.isLoading = false
		},

		async handleUnban(id) {
			await unbanActor(this.token, id)
			this.banList = this.banList.filter((ban) => ban.id !== id)
		},
	},
}
</script>

<style lang="scss" scoped>
.conversation-ban {
	&__content {
		min-height: 200px;
	}

	&__list {
		overflow: auto;
		height: calc(100% - 45px - 12px);
		padding: calc(var(--default-grid-baseline) * 2);
	}
}
</style>
