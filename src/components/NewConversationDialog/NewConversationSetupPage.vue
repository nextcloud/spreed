<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcTextField
			ref="conversationName"
			v-model="conversationName"
			:placeholder="t('spreed', 'Enter a name for this conversation')"
			:label="t('spreed', 'Name')"
			:error="!!nameErrorLabel"
			labelVisible
			@keydown.enter="$emit('handleEnter')" />
		<span v-if="nameErrorLabel" class="new-group-conversation__error">
			{{ nameErrorLabel }}
		</span>
		<NcTextArea
			v-model="conversationDescription"
			:placeholder="t('spreed', 'Enter a description for this conversation')"
			:label="t('spreed', 'Description')"
			:error="!!descriptionErrorLabel"
			resize="vertical"
			labelVisible />
		<span v-if="descriptionErrorLabel" class="new-group-conversation__error">
			{{ descriptionErrorLabel }}
		</span>

		<template v-if="supportsAvatar">
			<label class="new-group-conversation__label">
				{{ t('spreed', 'Picture') }}
			</label>
			<ConversationAvatarEditor
				ref="conversationAvatar"
				:conversation="newConversation"
				controlled
				editable
				@avatarEdited="$emit('avatarEdited', $event)" />
		</template>

		<template v-if="conversationTypeOptions.length > 0">
			<label class="new-group-conversation__label">
				{{ t('spreed', 'Conversation type') }}
			</label>
			<div class="conversation-type-selector">
				<button
					v-for="option in conversationTypeOptions"
					:key="option.value"
					class="conversation-type-selector__option"
					:class="[{ 'conversation-type-selector__option--active': preset === option.value }]"
					@click="preset = option.value">
					<span class="conversation-type-selector__header">
						<NcIconSvgWrapper v-if="option.svg" :svg="option.svg" :size="20" />
						<component :is="option.icon" v-else-if="option.icon" :size="20" />
						<span class="conversation-type-selector__label">{{ option.label }}</span>
					</span>
					<span class="conversation-type-selector__description">{{ option.description }}</span>
				</button>
			</div>
		</template>
		<div v-if="presetHiddenParameters.length" class="conversation-type-selector__summary">
			<span>{{ t('spreed', 'Default parameters are:') }}</span>
			<ul class="conversation-type-selector__summary-list">
				<li v-for="parameter in presetHiddenParameters" :key="parameter">
					{{ parameter }}
				</li>
			</ul>
			<span>{{ t('spreed', 'These settings can be changed once the conversation is created.') }}</span>
		</div>

		<label class="new-group-conversation__label">
			{{ t('spreed', 'Conversation visibility') }}
		</label>
		<NcCheckboxRadioSwitch
			v-model="isPublic"
			type="switch">
			{{ t('spreed', 'Allow guests to join via link') }}
		</NcCheckboxRadioSwitch>
		<div class="new-group-conversation__wrapper">
			<NcCheckboxRadioSwitch
				v-model="hasPassword"
				type="switch"
				:disabled="!isPublic || forcePasswordProtection">
				<span class="checkbox__label">{{ t('spreed', 'Password protection') }}</span>
			</NcCheckboxRadioSwitch>
			<NcPasswordField
				v-if="hasPassword"
				v-model="passwordValue"
				autocomplete="new-password"
				checkPasswordStrength
				:placeholder="t('spreed', 'Enter password')"
				:aria-label="t('spreed', 'Enter password')"
				@valid="$emit('isPasswordValid', true)"
				@invalid="$emit('isPasswordValid', false)" />
		</div>
		<ListableSettings v-model="listableValue" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconForumOutline from 'vue-material-design-icons/ForumOutline.vue'
import IconMonitorAccount from 'vue-material-design-icons/MonitorAccount.vue'
import IconPresentation from 'vue-material-design-icons/Presentation.vue'
import ConversationAvatarEditor from '../ConversationSettings/ConversationAvatarEditor.vue'
import ListableSettings from '../ConversationSettings/ListableSettings.vue'
import IconVolumeHighOutline from '../../../img/material-icons/volume-high-outline.svg?raw'
import { CONVERSATION } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useSettingsStore } from '../../stores/settings.ts'
import { messageExpirationOptions } from '../../utils/formattedTime.ts'
import generatePassword from '../../utils/generatePassword.ts'

const supportsAvatar = hasTalkFeature('local', 'avatar')
const forcePasswordProtection = getTalkConfig('local', 'conversations', 'force-passwords')
const maxDescriptionLength = getTalkConfig('local', 'conversations', 'description-length') || 500

const presetIcons = {
	[CONVERSATION.PRESET.DEFAULT]: { icon: IconForumOutline },
	[CONVERSATION.PRESET.VOICE_ROOM]: { svg: IconVolumeHighOutline },
	[CONVERSATION.PRESET.PRESENTATION]: { icon: IconPresentation },
	[CONVERSATION.PRESET.WEBINAR]: { icon: IconMonitorAccount },
}

/**
 *
 * @param seconds
 */
function formatExpiration(seconds) {
	const duration = messageExpirationOptions.find((option) => option.id === seconds)?.label
		?? t('spreed', 'Custom expiration time')
	return t('spreed', 'Message expiration set: {duration}', { duration })
}

/**
 *
 * @param key
 * @param value
 */
function formatHiddenParameter(key, value) {
	switch (key) {
		case 'messageExpiration':
			return value > 0 ? formatExpiration(value) : null
		case 'readOnly':
			return value === 1 ? t('spreed', 'This conversation is read-only') : null
		case 'lobbyState':
			return value === 1 ? t('spreed', 'Enable lobby, restricting the conversation to moderators') : null
		case 'recordingConsent':
			return value === 1 ? t('spreed', 'Require recording consent before joining call in this conversation') : null
		case 'sipEnabled':
			if (value === 1) {
				return t('spreed', 'Enable phone and SIP dial-in')
			}
			if (value === 2) {
				return [
					t('spreed', 'Enable phone and SIP dial-in'),
					t('spreed', 'Allow to dial-in without a PIN'),
				]
			}
			return null
		case 'mentionPermissions':
			return value === 1 ? t('spreed', 'Only moderators are allowed to mention @all') : null
		default:
			return null
	}
}
export default {

	name: 'NewConversationSetupPage',

	components: {
		ConversationAvatarEditor,
		ListableSettings,
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcTextArea,
		NcTextField,
		NcIconSvgWrapper,
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

	emits: ['update:newConversation', 'update:password', 'update:listable', 'avatarEdited', 'handleEnter', 'isPasswordValid'],

	setup() {
		const settingsStore = useSettingsStore()
		return {
			CONVERSATION,
			supportsAvatar,
			forcePasswordProtection,
			settingsStore,
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

		conversationTypeOptions() {
			return this.settingsStore.visiblePresets
				.map((preset) => ({
					value: preset.identifier,
					label: preset.name,
					description: preset.description,
					...presetIcons[preset.identifier],
				}))
		},

		presetHiddenParameters() {
			const preset = this.settingsStore.presets.find((p) => p.identifier === this.preset)
			if (!preset) {
				return []
			}
			const forcedParameters = { ...this.settingsStore.presets.find((p) => p.identifier === CONVERSATION.PRESET.FORCED)?.parameters }
			const labels = []
			for (const [key, value] of Object.entries(preset.parameters)) {
				if (key in forcedParameters) {
					continue
				}
				const label = formatHiddenParameter(key, value)
				if (label) {
					labels.push(...(Array.isArray(label) ? label : [label]))
				}
			}
			return labels
		},

		preset: {
			get() {
				return this.newConversation.preset ?? CONVERSATION.PRESET.DEFAULT
			},

			set(preset) {
				this.applyPresetParameters(preset)
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

	async created() {
		await this.settingsStore.fetchPresets()
	},

	methods: {
		t,
		// Inner method to update parent object
		updateNewConversation(data) {
			this.$emit('update:newConversation', { ...this.newConversation, ...data })
		},

		applyPresetParameters(preset) {
			const parameters = this.settingsStore.presets.find((p) => p.identifier === preset)?.parameters ?? {}

			let attributes = this.newConversation.attributes
			if (preset === CONVERSATION.PRESET.VOICE_ROOM) {
				attributes |= CONVERSATION.ATTRIBUTE.VOICE_ROOM
			} else {
				attributes &= ~CONVERSATION.ATTRIBUTE.VOICE_ROOM
			}

			const update = { attributes, preset }
			let nextListable = this.listable

			for (const [key, value] of Object.entries(parameters)) {
				if (key === 'listable') {
					nextListable = value
				} else if (key === 'roomType') {
					update.type = value
					if (value !== CONVERSATION.TYPE.PUBLIC) {
						update.hasPassword = false
					} else if (forcePasswordProtection) {
						update.hasPassword = true
					}
				} else {
					update[key] = value
				}
			}

			this.updateNewConversation(update)

			if (nextListable !== this.listable) {
				this.$emit('update:listable', nextListable)
			}
			if (update.type && update.type !== CONVERSATION.TYPE.PUBLIC) {
				this.$emit('update:password', '')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.new-group-conversation {
	&__wrapper {
		display: flex;
		gap: var(--default-grid-baseline);
		align-items: flex-end;

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
		font-weight: bold;
	}

	&__error {
		color: var(--color-text-error);
	}
}

.conversation-type-selector {
	display: grid;
	grid-template-columns: repeat(2, minmax(200px, 1fr));

	&__option {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 4px;
		padding: calc(var(--default-grid-baseline) * 2);
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-large);
		background: none;
		cursor: pointer;
		text-align: start;

		&:hover {
			background: var(--color-background-hover);
		}

		&--active {
			border-color: var(--color-primary-element);
		}

		&:only-child {
			grid-column: 1 / -1; // span a single card
		}
	}

	&__header {
		display: flex;
		align-items: center;
		gap: 4px;
		height: var(--default-clickable-area);
	}

	&__label {
		font-weight: bold;
	}

	&__description {
		color: var(--color-text-maxcontrast);
		font-size: small;
		font-weight: normal;
	}

	&__summary {
		margin-top: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
		font-size: small;
	}

	&__summary-list {
		margin: 0;
		padding-inline-start: calc(var(--default-grid-baseline) * 5);
		list-style: disc;
	}
}
</style>
