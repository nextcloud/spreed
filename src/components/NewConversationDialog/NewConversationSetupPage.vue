<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcTextField ref="conversationName"
			v-model="conversationName"
			:placeholder="t('spreed', 'Enter a name for this conversation')"
			:label="t('spreed', 'Name')"
			:error="!!nameErrorLabel"
			label-visible
			@keydown.enter="$emit('handle-enter')" />
		<span v-if="nameErrorLabel" class="new-group-conversation__error">
			{{ nameErrorLabel }}
		</span>
		<NcTextArea v-model="conversationDescription"
			:placeholder="t('spreed', 'Enter a description for this conversation')"
			:label="t('spreed', 'Description')"
			:error="!!descriptionErrorLabel"
			resize="vertical"
			label-visible />
		<span v-if="descriptionErrorLabel" class="new-group-conversation__error">
			{{ descriptionErrorLabel }}
		</span>

		<template v-if="supportsAvatar">
			<label class="avatar-editor__label">
				{{ t('spreed', 'Picture') }}
			</label>
			<ConversationAvatarEditor ref="conversationAvatar"
				:conversation="newConversation"
				controlled
				editable
				@avatar-edited="$emit('avatar-edited', $event)" />
		</template>

		<label class="new-group-conversation__label">
			{{ t('spreed', 'Conversation visibility') }}
		</label>
		<NcCheckboxRadioSwitch v-model="isPublic"
			type="switch">
			{{ t('spreed', 'Allow guests to join via link') }}
		</NcCheckboxRadioSwitch>
		<div class="new-group-conversation__wrapper">
			<NcCheckboxRadioSwitch v-model="hasPassword"
				type="switch"
				:disabled="!isPublic || forcePasswordProtection">
				<span class="checkbox__label">{{ t('spreed', 'Password protection') }}</span>
			</NcCheckboxRadioSwitch>
			<NcPasswordField v-if="hasPassword"
				v-model="passwordValue"
				autocomplete="new-password"
				check-password-strength
				:placeholder="t('spreed', 'Enter password')"
				:aria-label="t('spreed', 'Enter password')"
				@valid="$emit('is-password-valid', true)"
				@invalid="$emit('is-password-valid', false)" />
		</div>
		<ListableSettings v-model="listableValue" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ConversationAvatarEditor from '../ConversationSettings/ConversationAvatarEditor.vue'
import ListableSettings from '../ConversationSettings/ListableSettings.vue'
import { CONVERSATION } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import generatePassword from '../../utils/generatePassword.ts'

const supportsAvatar = hasTalkFeature('local', 'avatar')
const forcePasswordProtection = getTalkConfig('local', 'conversations', 'force-passwords')
const maxDescriptionLength = getTalkConfig('local', 'conversations', 'description-length') || 500
export default {

	name: 'NewConversationSetupPage',

	components: {
		ConversationAvatarEditor,
		ListableSettings,
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcTextArea,
		NcTextField,
	},

	props: {
		newConversation: {
			type: Object,
			required: true,
		},

		password: {
			type: String,
			required: true,
		},

		listable: {
			type: Number,
			required: true,
		},
	},

	emits: ['update:newConversation', 'update:password', 'update:listable', 'avatar-edited', 'handle-enter', 'is-password-valid'],

	setup() {
		return {
			supportsAvatar,
			forcePasswordProtection,
		}
	},

	computed: {
		conversationName: {
			get() {
				return this.newConversation.displayName
			},

			set(displayName) {
				this.updateNewConversation({ displayName })
			},
		},

		conversationDescription: {
			get() {
				return this.newConversation.description
			},

			set(description) {
				this.updateNewConversation({ description })
			},
		},

		nameErrorLabel() {
			if (this.conversationName.length <= CONVERSATION.MAX_NAME_LENGTH) {
				return
			}
			return t('spreed', 'Maximum length exceeded ({maxlength} characters)', { maxlength: CONVERSATION.MAX_NAME_LENGTH })
		},

		descriptionErrorLabel() {
			if (this.conversationDescription.length <= maxDescriptionLength) {
				return
			}
			return t('spreed', 'Maximum length exceeded ({maxlength} characters)', { maxlength: maxDescriptionLength })
		},

		isPublic: {
			get() {
				return this.newConversation.type === CONVERSATION.TYPE.PUBLIC
			},

			async set(value) {
				if (value) {
					this.updateNewConversation({ type: CONVERSATION.TYPE.PUBLIC, hasPassword: this.forcePasswordProtection ?? false })
					if (this.forcePasswordProtection) {
						// Make it easier to users by generating a password
						this.$emit('update:password', await generatePassword())
					}
				} else {
					this.updateNewConversation({ type: CONVERSATION.TYPE.GROUP, hasPassword: false })
				}
			},
		},

		hasPassword: {
			get() {
				return this.newConversation.hasPassword
			},

			set(value) {
				this.updateNewConversation({ hasPassword: value })
				if (!value) {
					this.$emit('update:password', '')
				}
			},
		},

		passwordValue: {
			get() {
				return this.password
			},

			set(value) {
				this.$emit('update:password', value)
			},
		},

		listableValue: {
			get() {
				return this.listable
			},

			set(value) {
				this.$emit('update:listable', value)
			},
		},
	},

	methods: {
		t,
		// Inner method to update parent object
		updateNewConversation(data) {
			this.$emit('update:newConversation', Object.assign({}, this.newConversation, data))
		},
	},
}
</script>

<style lang="scss" scoped>
.new-group-conversation {
	&__wrapper {
		display: flex;
		gap: var(--default-grid-baseline);
		align-items: flex-start;

		.checkbox__label {
			white-space: nowrap;
		}

		:deep(.input-field) {
			margin-top: 6px;
		}
	}

	&__label {
		display: block;
		margin-top: 10px;
		padding: 4px 0;
	}

	&__error {
		color: var(--color-error);
	}
}
</style>
