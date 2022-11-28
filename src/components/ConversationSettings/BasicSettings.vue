<template>
	<Fragment>
		<NcTextField :value.sync="conversationNameTextField"
			:label="t('spreed', 'Name')"
			:disabled="!isEditing"
			:label-visible="true" />
		<NcTextField :value.sync="descriptionTextField"
			:label="t('spreed', 'Description')"
			:placeholder="t('spreed', 'Enter a description for this conversation')"
			:disabled="!isEditing"
			:label-visible="true" />
		<div class="basic-settings__buttons">
			<NcButton v-if="!isEditing" type="secondary" @click="isEditing = true">
				{{ t('spreed', 'Edit basic settings') }}
			</NcButton>
			<template v-else-if="isEditing">
				<NcButton type="tertiary" @click="cancelEditing">
					{{ t('spreed', 'Cancel') }}
				</NcButton>
				<NcButton type="secondary" @click="saveSettings">
					{{ t('spreed', 'Save') }}
				</NcButton>
			</template>
		</div>
	</Fragment>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { Fragment } from 'vue-frag'

export default {
	name: 'BasicSettings',

	components: {
		NcTextField,
		NcButton,
		Fragment,
	},

	props: {
		/**
		 * The conversation object
		 */
		conversation: {
			type: Object,
			required: true,
		},

		/**
		 * The conversation token
		 */
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			isEditing: false,
			conversationNameTextField: '',
			descriptionTextField: '',
		}
	},

	watch: {
		// Update local values everytime the conversation object changes
		conversation() {
			if (this.isEditing) {
				// While editing, we do not want to update the local values
				// because the user is modifying them.
				return
			}
			this.updateLocalValues()
		},
	},

	mounted() {
		this.updateLocalValues()
	},

	methods: {
		updateLocalValues() {
			if (this.conversation.displayName !== this.conversationNameTextField) {
				this.conversationNameTextField = this.conversation.displayName

			}
			if (this.conversation.description !== this.descriptionTextField) {
				this.descriptionTextField = this.conversation.description ? this.conversation.description : ''
			}
		},

		cancelEditing() {
			this.updateLocalValues()
			this.isEditing = false
		},

		async saveSettings() {
			this.isEditing = true
			// Update conversation name if new
			if (this.conversationNameTextField !== this.conversation.displayName) {
				try {
					await this.$store.dispatch('setConversationName', {
						token: this.token,
						name: this.conversationNameTextField,
					})
				} catch (error) {
					console.error('Error while setting conversation name', error)
					showError(t('spreed', 'Error while updating conversation name'))
				}
			}
			// Update description if new
			if (this.descriptionTextField !== this.conversation.description) {
				try {
					await this.$store.dispatch('setConversationDescription', {
						token: this.token,
						description: this.descriptionTextField,
					})
				} catch (error) {
					console.error('Error while setting conversation description', error)
					showError(t('spreed', 'Error while updating conversation description'))
				}
			}
			this.updateLocalValues()
			this.isEditing = false
		},
	},
}
</script>

<style lang="scss" scoped>
.basic-settings {
	&__buttons {
		display: flex;
		gap: var(--default-grid-baseline);
		margin-top: calc(var(--default-grid-baseline) * 2);
	}
}
</style>
