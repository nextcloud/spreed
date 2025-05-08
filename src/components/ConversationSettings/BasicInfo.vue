<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Fragment>
		<!-- eslint-disable-next-line vue/no-v-html -->
		<p v-if="canFullModerate && isEventConversation" class="app-settings-section__hint" v-html="calendarHint" />
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Name') }}
		</h4>
		<EditableTextField :editable="canFullModerate && !isEventConversation"
			:initial-text="conversationName"
			:editing="isEditingName"
			:loading="isNameLoading"
			:placeholder="t('spreed', 'Enter a name for this conversation')"
			:edit-button-aria-label="t('spreed', 'Edit conversation name')"
			:max-length="CONVERSATION.MAX_NAME_LENGTH"
			@submit-text="handleUpdateName"
			@update:editing="handleEditName" />
		<template v-if="!isOneToOne">
			<h4 class="app-settings-section__subtitle">
				{{ t('spreed', 'Description') }}
			</h4>
			<EditableTextField :editable="canFullModerate && !isEventConversation"
				:initial-text="description"
				:editing="isEditingDescription"
				:loading="isDescriptionLoading"
				:edit-button-aria-label="t('spreed', 'Edit conversation description')"
				:placeholder="t('spreed', 'Enter a description for this conversation')"
				:max-length="maxDescriptionLength"
				multiline
				use-markdown
				@submit-text="handleUpdateDescription"
				@update:editing="handleEditDescription" />
		</template>
		<template v-if="supportsAvatar">
			<h4 class="app-settings-section__subtitle">
				{{ t('spreed', 'Picture') }}
			</h4>
			<ConversationAvatarEditor :conversation="conversation"
				:editable="canFullModerate" />
		</template>
	</Fragment>
</template>

<script>
import { Fragment } from 'vue-frag'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import ConversationAvatarEditor from './ConversationAvatarEditor.vue'
import EditableTextField from '../UIShared/EditableTextField.vue'

import { CONVERSATION } from '../../constants.ts'
import { hasTalkFeature, getTalkConfig } from '../../services/CapabilitiesManager.ts'

const supportsAvatar = hasTalkFeature('local', 'avatar')
const maxDescriptionLength = getTalkConfig('local', 'conversations', 'description-length') || 500

export default {
	name: 'BasicInfo',

	components: {
		EditableTextField,
		Fragment,
		ConversationAvatarEditor,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},

		canFullModerate: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		return {
			supportsAvatar,
			CONVERSATION,
			maxDescriptionLength
		}
	},

	data() {
		return {
			isEditingDescription: false,
			isDescriptionLoading: false,
			isEditingName: false,
			isNameLoading: false,
		}
	},

	computed: {
		isOneToOne() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		conversationName() {
			return this.conversation.displayName
		},

		description() {
			return this.conversation.description
		},

		token() {
			return this.conversation.token
		},

		calendarHint() {
			return t('spreed', 'You can change the title and the description in {linkstart}Calendar ↗{linkend}.')
				.replace('{linkstart}', `<a target="_blank" rel="noreferrer nofollow" class="external" href="${generateUrl('apps/calendar')}">`)
				.replace('{linkend}', '</a>')
		},

		isEventConversation() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.EVENT
		},
	},

	methods: {
		t,
		async handleUpdateName(name) {
			this.isNameLoading = true
			try {
				await this.$store.dispatch('setConversationName', {
					token: this.token,
					name,
				})
				this.isEditingName = false
			} catch (error) {
				console.error('Error while setting conversation name', error)
				showError(t('spreed', 'Error while updating conversation name'))
			}
			this.isNameLoading = false
		},

		handleEditName(payload) {
			this.isEditingName = payload
		},

		async handleUpdateDescription(description) {
			this.isDescriptionLoading = true
			try {
				await this.$store.dispatch('setConversationDescription', {
					token: this.token,
					description,
				})
				this.isEditingDescription = false
			} catch (error) {
				console.error('Error while setting conversation description', error)
				showError(t('spreed', 'Error while updating conversation description'))
			}
			this.isDescriptionLoading = false
		},

		handleEditDescription(payload) {
			this.isEditingDescription = payload
		},
	},
}
</script>
