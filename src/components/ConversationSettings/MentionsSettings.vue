<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="canModerate">
		<NcCheckboxRadioSwitch
			:model-value="mentionPermissions === MENTION_PERMISSIONS.EVERYONE"
			:disabled="isMentionPermissionsLoading"
			type="switch"
			@update:model-value="toggleMentionPermissions">
			{{ t('spreed', 'Allow participants to mention @all') }}
		</NcCheckboxRadioSwitch>
	</div>

	<div v-else>
		<h5 class="app-settings-section__subtitle">
			{{ t('spreed', 'Mention permissions') }}
		</h5>
		<p>{{ summaryLabel }}</p>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { CONVERSATION } from '../../constants.ts'

export default {
	name: 'MentionsSettings',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		token: {
			type: String,
			default: null,
		},

		canModerate: {
			type: Boolean,
			default: true,
		},
	},

	setup() {
		const { MENTION_PERMISSIONS } = CONVERSATION
		return {
			MENTION_PERMISSIONS,
		}
	},

	data() {
		return {
			mentionPermissions: null,
			isMentionPermissionsLoading: false,
			lastNotification: null,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		summaryLabel() {
			switch (this.mentionPermissions) {
				case CONVERSATION.MENTION_PERMISSIONS.MODERATORS:
					return t('spreed', 'Only moderators are allowed to mention @all')
				case CONVERSATION.MENTION_PERMISSIONS.EVERYONE:
				default:
					return t('spreed', 'All participants are allowed to mention @all')
			}
		},
	},

	watch: {
		conversation: {
			immediate: true,
			handler() {
				this.mentionPermissions = this.conversation.mentionPermissions
			},
		},
	},

	beforeUnmount() {
		if (this.lastNotification) {
			this.lastNotification.hideToast()
			this.lastNotification = null
		}
	},

	methods: {
		t,
		async toggleMentionPermissions(checked) {
			const mentionPermissions = checked ? this.MENTION_PERMISSIONS.EVERYONE : this.MENTION_PERMISSIONS.MODERATORS
			if (!this.token) {
				this.mentionPermissions = mentionPermissions
				return
			}
			this.isMentionPermissionsLoading = true
			try {
				await this.$store.dispatch('setMentionPermissions', {
					token: this.token,
					mentionPermissions,
				})

				if (this.lastNotification) {
					this.lastNotification.hideToast()
					this.lastNotification = null
				}
				if (mentionPermissions === CONVERSATION.MENTION_PERMISSIONS.EVERYONE) {
					this.lastNotification = showSuccess(t('spreed', 'Participants are now allowed to mention @all.'))
				} else if (mentionPermissions === CONVERSATION.MENTION_PERMISSIONS.MODERATORS) {
					this.lastNotification = showSuccess(t('spreed', 'Mentioning @all has been limited to moderators.'))
				}
				this.mentionPermissions = mentionPermissions
			} catch (e) {
				console.error('Error occurred when opening or limiting the conversation', e)
				showError(t('spreed', 'Error occurred when opening or limiting the conversation'))
				this.mentionPermissions = this.conversation.mentionPermissions
			}
			this.isMentionPermissionsLoading = false
		},
	},

}
</script>
