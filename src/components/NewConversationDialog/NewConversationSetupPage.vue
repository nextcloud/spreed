<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div>
		<NcTextField ref="conversationName"
			v-model="conversationName"
			:placeholder="t('spreed', 'Enter a name for this conversation')"
			:label="t('spreed', 'Name')"
			label-visible
			@keydown.enter="$emit('handle-enter')" />
		<NcTextArea v-model="conversationDescription"
			:placeholder="t('spreed', 'Enter a description for this conversation')"
			:label="t('spreed', 'Description')"
			label-visible />

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
		<NcCheckboxRadioSwitch :checked.sync="isPublic"
			type="switch">
			{{ t('spreed', 'Allow guests to join via link') }}
		</NcCheckboxRadioSwitch>
		<div class="new-group-conversation__wrapper">
			<NcCheckboxRadioSwitch :checked.sync="hasPassword"
				type="switch"
				:disabled="!isPublic">
				<span class="checkbox__label">{{ t('spreed', 'Password protect') }}</span>
			</NcCheckboxRadioSwitch>
			<NcPasswordField v-if="hasPassword"
				v-model="passwordValue"
				autocomplete="new-password"
				check-password-strength
				:placeholder="t('spreed', 'Enter password')"
				:aria-label="t('spreed', 'Enter password')" />
		</div>
		<ListableSettings v-model="listableValue" />
	</div>
</template>

<script>
import { getCapabilities } from '@nextcloud/capabilities'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import ConversationAvatarEditor from '../ConversationSettings/ConversationAvatarEditor.vue'
import ListableSettings from '../ConversationSettings/ListableSettings.vue'

import { CONVERSATION } from '../../constants.js'

const supportsAvatar = getCapabilities()?.spreed?.features?.includes('avatar')

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
		}
	},

	emits: ['update:newConversation', 'update:password', 'update:listable', 'avatar-edited', 'handle-enter'],

	setup() {
		return { supportsAvatar }
	},

	computed: {
		conversationName: {
			get() {
				return this.newConversation.displayName
			},
			set(event) {
				this.updateNewConversation({ displayName: event.target.value })
			},
		},

		conversationDescription: {
			get() {
				return this.newConversation.description
			},
			set(event) {
				this.updateNewConversation({ description: event.target.value })
			},
		},

		isPublic: {
			get() {
				return this.newConversation.type === CONVERSATION.TYPE.PUBLIC
			},
			set(value) {
				if (value) {
					this.updateNewConversation({ type: CONVERSATION.TYPE.PUBLIC })
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
			set(event) {
				this.$emit('update:password', event.target.value)
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
		align-items: center;

		.checkbox__label {
			white-space: nowrap;
		}
	}

	&__label {
		display: block;
		margin-top: 10px;
		padding: 4px 0;
	}
}
</style>
