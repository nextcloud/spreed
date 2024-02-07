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
				<li v-for="item of invitations"
					:key="`invitation_${item.id}`"
					class="inbox__item">
					<ConversationIcon :item="item" />
					<div class="inbox__item-desc">
						<span class="inbox__item-desc__name">
							{{ item.roomName }}
						</span>
						<span class="inbox__item-desc__subname">
							{{ t('spreed', 'From {user} at {remoteServer}', {
								user: item.inviterDisplayName,
								remoteServer: item.remoteServerUrl,
							}) }}
						</span>
					</div>
					<NcButton type="tertiary"
						aria-label="t('spreed', 'Decline invitation')"
						title="t('spreed', 'Decline invitation')"
						:disabled="isLoading"
						@click="rejectShare(item.id)">
						<template #icon>
							<NcLoadingIcon v-if="isLoading" :size="20" />
							<CancelIcon v-else :size="20" />
						</template>
					</NcButton>
					<NcButton type="primary"
						aria-label="t('spreed', 'Accept invitation')"
						:disabled="isLoading"
						@click="acceptShare(item.id)">
						<template #icon>
							<NcLoadingIcon v-if="isLoading" :size="20" />
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

import ConversationIcon from '../ConversationIcon.vue'

import { CONVERSATION } from '../../constants.js'
import { useFederationStore } from '../../stores/federation.js'

export default {
	name: 'InvitationHandler',

	components: {
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
			isLoading: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		invitations() {
			return Object.values(this.federationStore.pendingShares)
				.map(item => ({
					...item,
					type: CONVERSATION.TYPE.GROUP,
				}))
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
			this.isLoading = true
			const conversation = await this.federationStore.acceptShare(id)
			this.isLoading = false
			if (conversation?.token) {
				this.$store.dispatch('addConversation', conversation)
				this.$router.push({ name: 'conversation', params: { token: conversation.token } })
				this.closeModal()
			}
		},

		async rejectShare(id) {
			this.isLoading = true
			await this.federationStore.rejectShare(id)
			this.isLoading = false
			if (this.invitations.length === 0) {
				this.closeModal()
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
