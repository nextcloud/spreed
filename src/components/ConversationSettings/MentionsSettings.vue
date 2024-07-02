<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="canModerate">
		<NcCheckboxRadioSwitch :checked="mentionPermissions === MENTION_PERMISSIONS.EVERYONE"
			:disabled="isMentionPermissionsLoading"
			type="switch"
			@update:checked="toggleMentionPermissions">
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

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { CONVERSATION } from '../../constants.js'

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

		value: {
			type: Number,
			default: null,
		},
	},

	emits: ['input'],

	data() {
		return {
			mentionPermissions: null,
			isMentionPermissionsLoading: false,
			lastNotification: null,
			MENTION_PERMISSIONS: CONVERSATION.MENTION_PERMISSIONS,
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
				return t('spreed', 'All the participants are allowed to mention @all')
			}
		}
	},

	watch: {
		value(value) {
			this.mentionPermissions = value
		},

		conversation: {
			immediate: true,
			handler() {
				this.mentionPermissions = this.conversation.mentionPermissions
			},
		},
	},

	mounted() {
		if (this.token) {
			this.mentionPermissions = this.value || this.conversation.mentionPermissions
		} else {
			this.mentionPermissions = this.value
		}
	},

	beforeDestroy() {
		if (this.lastNotification) {
			this.lastNotification.hideToast()
			this.lastNotification = null
		}
	},

	methods: {
		t,
		async toggleMentionPermissions(checked) {
			await this.saveMentionPermissions(checked ? this.MENTION_PERMISSIONS.EVERYONE : this.MENTION_PERMISSIONS.MODERATORS)
		},

		async saveMentionPermissions(mentionPermissions) {
			this.$emit('input', mentionPermissions)
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

<style lang="scss" scoped>
.additional-top-margin {
	margin-top: 10px;
}
</style>
