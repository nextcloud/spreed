<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcNoteCard type="warning" :text="t('spreed', 'Be careful, these actions cannot be undone.')" />
		<div class="danger-zone">
			<div v-if="canLeaveConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Leave conversation') }}
				</h4>
				<p class="app-settings-section__hint">
					{{ t('spreed', 'Once a conversation is left, to rejoin a closed conversation, an invite is needed. An open conversation can be rejoined at any time.') }}
				</p>
				<NcButton variant="warning" @click="toggleShowLeaveConversationDialog">
					{{ t('spreed', 'Leave conversation') }}
				</NcButton>
				<NcDialog class="danger-zone__dialog"
					:open.sync="isLeaveConversationDialogOpen"
					:name="t('spreed', 'Leave conversation')"
					container=".danger-zone">
					<template #default>
						<p>{{ leaveConversationDialogMessage }}</p>
						<p v-if="supportsArchive && !conversation.isArchived">
							{{ t('spreed', 'You can archive this conversation instead.') }}
						</p>
					</template>
					<template #actions>
						<NcButton variant="tertiary" @click="toggleShowLeaveConversationDialog">
							{{ t('spreed', 'No') }}
						</NcButton>
						<NcButton v-if="supportsArchive && !conversation.isArchived" variant="secondary" @click="toggleArchiveConversation">
							{{ t('spreed', 'Archive conversation') }}
						</NcButton>
						<NcButton variant="warning" @click="leaveConversation">
							{{ t('spreed', 'Yes') }}
						</NcButton>
					</template>
				</NcDialog>
			</div>
			<div v-if="canDeleteConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Delete conversation') }}
				</h4>
				<p class="app-settings-section__hint">
					{{ t('spreed', 'Permanently delete this conversation.') }}
				</p>
				<NcButton variant="error"
					@click="toggleShowDeleteConversationDialog">
					{{ t('spreed', 'Delete conversation') }}
				</NcButton>
				<NcDialog class="danger-zone__dialog"
					:open.sync="isDeleteConversationDialogOpen"
					:name="t('spreed', 'Delete conversation')"
					:message="deleteConversationDialogMessage"
					container=".danger-zone">
					<template #actions>
						<NcButton variant="tertiary" @click="toggleShowDeleteConversationDialog">
							{{ t('spreed', 'No') }}
						</NcButton>
						<NcButton variant="error" @click="deleteConversation">
							{{ t('spreed', 'Yes') }}
						</NcButton>
					</template>
				</NcDialog>
			</div>
			<div v-if="canDeleteConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Delete chat messages') }}
				</h4>
				<p class="app-settings-section__hint">
					{{ t('spreed', 'Permanently delete all the messages in this conversation.') }}
				</p>
				<NcButton variant="error"
					@click="toggleShowDeleteChatDialog">
					{{ t('spreed', 'Delete chat messages') }}
				</NcButton>
				<NcDialog class="danger-zone__dialog"
					:open.sync="isDeleteChatDialogOpen"
					:name="t('spreed', 'Delete all chat messages')"
					:message="deleteChatDialogMessage"
					container=".danger-zone">
					<template #actions>
						<NcButton variant="tertiary" @click="toggleShowDeleteChatDialog">
							{{ t('spreed', 'No') }}
						</NcButton>
						<NcButton variant="error" @click="clearChatHistory">
							{{ t('spreed', 'Yes') }}
						</NcButton>
					</template>
				</NcDialog>
			</div>
			<div />
		</div>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { useGetToken } from '../../composables/useGetToken.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useTokenStore } from '../../stores/token.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

export default {
	name: 'DangerZone',
	components: {
		NcButton,
		NcNoteCard,
		NcDialog,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},

		canLeaveConversation: {
			type: Boolean,
			required: true,
		},

		canDeleteConversation: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const isLeaveConversationDialogOpen = ref(false)
		const isDeleteConversationDialogOpen = ref(false)
		const isDeleteChatDialogOpen = ref(false)

		return {
			supportsArchive,
			isLeaveConversationDialogOpen,
			isDeleteConversationDialogOpen,
			isDeleteChatDialogOpen,
			token: useGetToken(),
			tokenStore: useTokenStore(),
		}
	},

	computed: {
		container() {
			return '#conversation-settings-container'
		},

		leaveConversationDialogMessage() {
			return t('spreed', 'Do you really want to leave "{displayName}"?', this.conversation, undefined, {
				escape: false,
				sanitize: false,
			})
		},

		deleteConversationDialogMessage() {
			return t('spreed', 'Do you really want to delete "{displayName}"?', this.conversation, undefined, {
				escape: false,
				sanitize: false,
			})
		},

		deleteChatDialogMessage() {
			return t('spreed', 'Do you really want to delete all messages in "{displayName}"?', this.conversation, undefined, {
				escape: false,
				sanitize: false,
			})
		},
	},

	methods: {
		t,

		hideConversationSettings() {
			emit('hide-conversation-settings')
		},

		/**
		 * Deletes the current user from the conversation.
		 */
		async toggleArchiveConversation() {
			this.isLeaveConversationDialogOpen = false

			await this.$store.dispatch('toggleArchive', this.conversation)
			this.hideConversationSettings()
		},

		/**
		 * Deletes the current user from the conversation.
		 */
		async leaveConversation() {
			this.isLeaveConversationDialogOpen = false

			try {
				await this.$store.dispatch('removeCurrentUserFromConversation', { token: this.conversation.token })
				this.hideConversationSettings()
			} catch (error) {
				if (error?.response?.status === 400) {
					showError(t('spreed', 'You need to promote a new moderator before you can leave the conversation'))
				} else {
					console.error(`error while removing yourself from conversation ${error}`)
				}
			}
		},

		/**
		 * Deletes the conversation.
		 */
		async deleteConversation() {
			this.isDeleteConversationDialogOpen = false

			if (this.token === this.conversation.token) {
				this.$router.push({ name: 'root' })
				this.tokenStore.updateToken('')
			}

			try {
				await this.$store.dispatch('deleteConversationFromServer', { token: this.conversation.token })
				// Close the settings
				this.hideConversationSettings()
			} catch (error) {
				console.debug(`error while deleting conversation ${error}`)
				showError(t('spreed', 'Error while deleting conversation'))
			}
		},

		/**
		 * Clears the chat history
		 */
		async clearChatHistory() {
			try {
				await this.$store.dispatch('clearConversationHistory', { token: this.conversation.token })
				this.isDeleteChatDialogOpen = false
				// Close the settings
				this.hideConversationSettings()
			} catch (error) {
				console.debug(`error while clearing chat history ${error}`)
				showError(t('spreed', 'Error while clearing chat history'))
			}
		},

		toggleShowLeaveConversationDialog() {
			this.isLeaveConversationDialogOpen = !this.isLeaveConversationDialogOpen
		},

		toggleShowDeleteConversationDialog() {
			this.isDeleteConversationDialogOpen = !this.isDeleteConversationDialogOpen
		},

		toggleShowDeleteChatDialog() {
			this.isDeleteChatDialogOpen = !this.isDeleteChatDialogOpen
		},
	},
}
</script>

<style lang="scss" scoped>
h4 {
	font-weight: bold;
}

.danger-zone {
	&__dialog {
		:deep(.modal-container) {
			padding-block: 4px 8px;
			padding-inline: 12px 8px;
		}
	}
}

</style>
