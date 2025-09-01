<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		v-model:open="modal"
		:name="t('spreed', 'Pending invitations')"
		size="normal"
		close-on-click-outside>
		<div class="inbox">
			<p class="inbox__disclaimer">
				{{ t('spreed', 'Join conversations from remote Nextcloud servers') }}
			</p>
			<ul v-if="invitationsLoadedCount" class="inbox__list">
				<li
					v-for="(item, id) in invitations"
					:key="`invitation_${id}`"
					class="inbox__item">
					<ConversationIcon :item="item" hide-user-status />
					<div class="inbox__item-desc">
						<span class="inbox__item-desc__name">
							{{ item.roomName }}
						</span>
						<NcRichText
							class="inbox__item-desc__subname"
							:text="t('spreed', 'From {user} at {remoteServer}', { remoteServer: item.remoteServer })"
							:arguments="getRichParameters(item)"
							:reference-limit="0" />
					</div>
					<NcButton
						variant="tertiary"
						class="inbox__item-button"
						:aria-label="t('spreed', 'Decline invitation')"
						:title="t('spreed', 'Decline invitation')"
						:disabled="!!item.loading"
						@click="rejectShare(id)">
						<template #icon>
							<NcLoadingIcon v-if="item.loading === 'reject'" :size="20" />
							<CancelIcon v-else :size="20" />
						</template>
					</NcButton>
					<NcButton
						variant="primary"
						class="inbox__item-button"
						:aria-label="t('spreed', 'Accept invitation')"
						:title="t('spreed', 'Accept invitation')"
						:disabled="!!item.loading"
						@click="acceptShare(id)">
						<template #icon>
							<NcLoadingIcon v-if="item.loading === 'accept'" :size="20" />
							<CheckIcon v-else :size="20" />
						</template>
						{{ t('spreed', 'Accept') }}
					</NcButton>
				</li>
			</ul>
			<NcEmptyContent v-else class="inbox__placeholder">
				<template #icon>
					<NcLoadingIcon v-if="isLoading" />
					<WebIcon v-else />
				</template>

				<template #description>
					<p>{{ isLoading ? t('spreed', 'Loading …') : t('spreed', 'No pending invitations') }}</p>
				</template>
			</NcEmptyContent>
		</div>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import CancelIcon from 'vue-material-design-icons/Cancel.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import WebIcon from 'vue-material-design-icons/Web.vue'
import ConversationIcon from '../ConversationIcon.vue'
import Mention from '../MessagesList/MessagesGroup/Message/MessagePart/Mention.vue'
import { CONVERSATION } from '../../constants.ts'
import { useFederationStore } from '../../stores/federation.ts'

export default {
	name: 'InvitationHandler',

	components: {
		ConversationIcon,
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcRichText,
		// Icons
		CancelIcon,
		CheckIcon,
		WebIcon,
	},

	expose: ['showModal'],

	setup() {
		const modal = ref(false)
		const isLoading = ref(true)

		return {
			federationStore: useFederationStore(),
			modal,
			isLoading,
		}
	},

	computed: {
		invitations() {
			const invitations = {}
			for (const id in this.federationStore.pendingShares) {
				const { localToken: token, remoteServerUrl: remoteServer, ...rest } = this.federationStore.pendingShares[id]
				invitations[id] = { ...rest, token, remoteServer, type: CONVERSATION.TYPE.GROUP }
			}
			return invitations
		},

		invitationsLoadedCount() {
			return Object.keys(this.invitations).length
		},
	},

	methods: {
		t,

		async showModal() {
			this.modal = true

			this.isLoading = true
			await this.federationStore.getShares()
			this.isLoading = false
		},

		closeModal() {
			this.modal = false
		},

		async acceptShare(id) {
			const conversation = await this.federationStore.acceptShare(id)
			if (conversation?.token) {
				this.$store.dispatch('addConversation', conversation)
				// TODO move cacheConversations to the store action
				this.$store.dispatch('cacheConversations')
			}
			this.checkIfNoMoreInvitations()
		},

		async rejectShare(id) {
			await this.federationStore.rejectShare(id)
			this.checkIfNoMoreInvitations()
		},

		checkIfNoMoreInvitations() {
			if (this.invitationsLoadedCount === 0) {
				this.closeModal()
			}
		},

		getRichParameters(item) {
			const [id, server] = item.inviterCloudId.split('@')
			return {
				user: {
					component: Mention,
					props: { id, name: item.inviterDisplayName, server, token: item.token || 'new', type: 'user' },
				},
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.inbox {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 100%;
	max-height: 700px;
	padding-bottom: calc(3 * var(--default-grid-baseline));

	&__disclaimer {
		margin-bottom: 12px;
		color: var(--color-text-maxcontrast)
	}

	& > &__placeholder {
		margin: 20px 0;
		padding: 0;
	}

	&__list {
		display: flex;
		flex-direction: column;
		gap: 8px;
		width: 100%;
		height: 100%;
		flex: 0 1 auto;
		overflow-y: auto;
	}

	&__item {
		display: flex;
		align-items: center;
		gap: 4px;
		padding: 8px 0;
		border-bottom: 1px solid var(--color-border);
		&:last-child {
			border-bottom: none;
		}

		&-desc {
			display: flex;
			flex-direction: column;
			margin-inline-end: auto;
			padding-inline-start: 4px;

			&__name {
				line-height: 20px;
				font-weight: bold;
				color: var(--color-main-text);
			}

			&__subname {
				// Overwrite NcRichText styles
				line-height: 20px !important;
				color: var(--color-text-maxcontrast);
			}
		}

		&-button {
			flex-shrink: 0;
		}
	}
}
</style>
