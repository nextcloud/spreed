<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<NcModal v-if="modal"
		:container="container"
		@close="closeModal">
		<div class="inbox">
			<h2 class="inbox__heading">
				{{ t('spreed', 'Pending invitations') }}
			</h2>
			<p class="inbox__disclaimer">
				{{ t('spreed', 'Join conversations from remote Nextcloud servers') }}
			</p>
			<ul class="inbox__list">
				<li v-for="(item, id) in invitations"
					:key="`invitation_${id}`"
					class="inbox__item">
					<ConversationIcon :item="item" hide-user-status />
					<div class="inbox__item-desc">
						<span class="inbox__item-desc__name">
							{{ item.roomName }}
						</span>
						<NcRichText class="inbox__item-desc__subname"
							:text="t('spreed', 'From {user} at {remoteServer}', { remoteServer: item.remoteServer })"
							:arguments="getRichParameters(item)"
							:reference-limit="0" />
					</div>
					<NcButton type="tertiary"
						:aria-label="t('spreed', 'Decline invitation')"
						:title="t('spreed', 'Decline invitation')"
						:disabled="!!item.loading"
						@click="rejectShare(id)">
						<template #icon>
							<NcLoadingIcon v-if="item.loading === 'reject'" :size="20" />
							<CancelIcon v-else :size="20" />
						</template>
					</NcButton>
					<NcButton type="primary"
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
		</div>
	</NcModal>
</template>

<script>
import CancelIcon from 'vue-material-design-icons/Cancel.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'

import ConversationIcon from '../ConversationIcon.vue'
import Mention from '../MessagesList/MessagesGroup/Message/MessagePart/Mention.vue'

import { CONVERSATION } from '../../constants.js'
import { useFederationStore } from '../../stores/federation.ts'

export default {
	name: 'InvitationHandler',

	components: {
		NcRichText,
		ConversationIcon,
		NcButton,
		NcLoadingIcon,
		NcModal,
		// Icons
		CancelIcon,
		CheckIcon,
	},

	setup() {
		return {
			federationStore: useFederationStore(),
		}
	},

	data() {
		return {
			modal: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		invitations() {
			const invitations = {}
			for (const id in this.federationStore.pendingShares) {
				const { localToken: token, remoteServerUrl: remoteServer, ...rest } = this.federationStore.pendingShares[id]
				invitations[id] = { ...rest, token, remoteServer, type: CONVERSATION.TYPE.GROUP }
			}
			return invitations
		},
	},

	expose: ['showModal'],

	methods: {
		showModal() {
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		async acceptShare(id) {
			const conversation = await this.federationStore.acceptShare(id)
			if (conversation?.token) {
				this.$store.dispatch('addConversation', conversation)
			}
			this.checkIfNoMoreInvitations()
		},

		async rejectShare(id) {
			await this.federationStore.rejectShare(id)
			this.checkIfNoMoreInvitations()
		},

		checkIfNoMoreInvitations() {
			if (Object.keys(this.invitations).length === 0) {
				this.closeModal()
			}
		},

		getRichParameters(item) {
			const [id, server] = item.inviterCloudId.split('@')
			return {
				user: {
					component: Mention,
					props: { id, name: item.inviterDisplayName, server, token: item.token, type: 'user' }
				}
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
	padding: 20px;

	&__heading {
		margin-bottom: 4px;
	}

	&__disclaimer {
		margin-bottom: 12px;
		color: var(--color-text-maxcontrast)
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
			margin-right: auto;
			padding-left: 4px;

			&__name {
				font-weight: bold;
				color: var(--color-main-text);
			}

			&__subname {
				color: var(--color-text-maxcontrast);
			}
		}
	}
}
</style>
