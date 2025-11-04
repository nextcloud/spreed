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
				<NcButton variant="warning" @click="leaveConversation">
					{{ t('spreed', 'Leave conversation') }}
				</NcButton>
			</div>
			<div v-if="canDeleteConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Delete conversation') }}
				</h4>
				<p class="app-settings-section__hint">
					{{ t('spreed', 'Permanently delete this conversation.') }}
				</p>
				<NcButton
					variant="error"
					@click="deleteConversation">
					{{ t('spreed', 'Delete conversation') }}
				</NcButton>
			</div>
			<div v-if="canDeleteConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Delete chat messages') }}
				</h4>
				<p class="app-settings-section__hint">
					{{ t('spreed', 'Permanently delete all the messages in this conversation.') }}
				</p>
				<NcButton
					variant="error"
					@click="clearChatHistory">
					{{ t('spreed', 'Delete chat messages') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import ConfirmDialog from '../UIShared/ConfirmDialog.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useTokenStore } from '../../stores/token.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

export default {
	name: 'DangerZone',
	components: {
		NcButton,
		NcNoteCard,
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
		return {
			token: useGetToken(),
			tokenStore: useTokenStore(),
		}
	},

	methods: {
		t,

		hideConversationSettings() {
			emit('hide-conversation-settings')
		},

		/**
		 * Archives the current conversation.
		 */
		async toggleArchiveConversation() {
			await this.$store.dispatch('toggleArchive', this.conversation)
			this.hideConversationSettings()
		},

		/**
		 * Deletes the current user from the conversation.
		 */
		async leaveConversation() {
			const customMessages = [
				t('spreed', 'Do you really want to leave "{displayName}"?', {
					displayName: this.conversation.displayName,
				}, { escape: false, sanitize: false }),
			]

			const buttons = [
				{ label: t('spreed', 'No'), variant: 'tertiary', callback: () => undefined },
				{ label: t('spreed', 'Yes'), variant: 'warning', callback: () => true },
			]

			if (supportsArchive && !this.conversation.isArchived) {
				// Offer archiving option as an alternative to leaving the conversation
				customMessages.push(t('spreed', 'You can archive this conversation instead.'))
				buttons.splice(1, 0, {
					label: t('spreed', 'Archive conversation'),
					variant: 'secondary',
					callback: () => {
						this.toggleArchiveConversation()
						return undefined
					},
				})
			}

			const confirmLeaveConversation = await spawnDialog(ConfirmDialog, {
				container: '.danger-zone',
				name: t('spreed', 'Leave conversation'),
				customMessages,
				buttons,
			})

			if (!confirmLeaveConversation) {
				return
			}

			if (this.token === this.conversation.token) {
				this.$router.push({ name: 'root' })
			}

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
			const confirmDeleteConversation = await spawnDialog(ConfirmDialog, {
				container: '.danger-zone',
				name: t('spreed', 'Delete conversation'),
				message: t('spreed', 'Do you really want to delete "{displayName}"?', {
					displayName: this.conversation.displayName,
				}, { escape: false, sanitize: false }),
				buttons: [
					{ label: t('spreed', 'No'), variant: 'tertiary', callback: () => undefined },
					{ label: t('spreed', 'Yes'), variant: 'error', callback: () => true },
				],
			})

			if (!confirmDeleteConversation) {
				return
			}

			if (this.token === this.conversation.token) {
				this.$router.push({ name: 'root' })
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
			const confirmDeleteChatMessages = await spawnDialog(ConfirmDialog, {
				container: '.danger-zone',
				name: t('spreed', 'Delete all chat messages'),
				message: t('spreed', 'Do you really want to delete all messages in "{displayName}"?', {
					displayName: this.conversation.displayName,
				}, { escape: false, sanitize: false }),
				buttons: [
					{ label: t('spreed', 'No'), variant: 'tertiary', callback: () => undefined },
					{ label: t('spreed', 'Yes'), variant: 'error', callback: () => true },
				],
			})

			if (!confirmDeleteChatMessages) {
				return
			}

			try {
				await this.$store.dispatch('clearConversationHistory', { token: this.conversation.token })
				// Close the settings
				this.hideConversationSettings()
			} catch (error) {
				console.debug(`error while clearing chat history ${error}`)
				showError(t('spreed', 'Error while clearing chat history'))
			}
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
