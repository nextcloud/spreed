<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcNoteCard type="warning">
			<p>{{ t('spreed', 'Be careful, these actions cannot be undone.') }}</p>
		</NcNoteCard>
		<div class="danger-zone">
			<div v-if="canLeaveConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Leave conversation') }}
				</h4>
				<p class="danger-zone__hint">
					{{ t('spreed', 'Once a conversation is left, to rejoin a closed conversation, an invite is needed. An open conversation can be rejoined at any time.') }}
				</p>
				<NcButton type="warning" @click="leaveConversation">
					{{ t('spreed', 'Leave conversation') }}
				</NcButton>
			</div>
			<div v-if="canDeleteConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Delete conversation') }}
				</h4>
				<p class="danger-zone__hint">
					{{ t('spreed', 'Permanently delete this conversation.') }}
				</p>
				<NcButton type="error"
					@click="toggleShowDeleteConversationDialog">
					{{ t('spreed', 'Delete conversation') }}
				</NcButton>
				<NcDialog class="danger-zone__dialog"
					:open.sync="isDeleteConversationDialogOpen"
					:name="t('spreed','Delete conversation')"
					:message="deleteConversationDialogMessage"
					container=".danger-zone">
					<template #actions>
						<NcButton type="tertiary" @click="toggleShowDeleteConversationDialog">
							{{ t('spreed', 'No') }}
						</NcButton>
						<NcButton type="error" @click="deleteConversation">
							{{ t('spreed', 'Yes') }}
						</NcButton>
					</template>
				</NcDialog>
			</div>
			<div v-if="canDeleteConversation" class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Delete chat messages') }}
				</h4>
				<p class="danger-zone__hint">
					{{ t('spreed', 'Permanently delete all the messages in this conversation.') }}
				</p>
				<NcButton type="error"
					@click="toggleShowDeleteChatDialog">
					{{ t('spreed', 'Delete chat messages') }}
				</NcButton>
				<NcDialog class="danger-zone__dialog"
					:open.sync="isDeleteChatDialogOpen"
					:name="t('spreed','Delete all chat messages')"
					:message="deleteChatDialogMessage"
					container=".danger-zone">
					<template #actions>
						<NcButton type="tertiary" @click="toggleShowDeleteChatDialog">
							{{ t('spreed', 'No') }}
						</NcButton>
						<NcButton type="error" @click="clearChatHistory">
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
// eslint-disable-next-line
// import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

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

	data() {
		return {
			isDeleteConversationDialogOpen: false,
			isDeleteChatDialogOpen: false,
		}
	},

	computed: {
		container() {
			return '#conversation-settings-container'
		},

		token() {
			return this.conversation.token
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
		}
	},

	methods: {

		hideConversationSettings() {
			emit('hide-conversation-settings')
		},
		/**
		 * Deletes the current user from the conversation.
		 */
		async leaveConversation() {
			try {
				await this.$store.dispatch('removeCurrentUserFromConversation', { token: this.token })
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

			if (this.token === this.$store.getters.getToken()) {
				this.$router.push({ name: 'root' })
				this.$store.dispatch('updateToken', '')
			}

			try {
				await this.$store.dispatch('deleteConversationFromServer', { token: this.token })
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
				await this.$store.dispatch('clearConversationHistory', { token: this.token })
				this.isDeleteChatDialogOpen = false
				// Close the settings
				this.hideConversationSettings()
			} catch (error) {
				console.debug(`error while clearing chat history ${error}`)
				showError(t('spreed', 'Error while clearing chat history'))
			}
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
	&__hint {
		color: var(--color-text-maxcontrast);
	}
	&__dialog {
		:deep(.modal-container) {
			padding-block: 4px 8px;
			padding-inline: 12px 8px;
		}
	}
}

</style>
