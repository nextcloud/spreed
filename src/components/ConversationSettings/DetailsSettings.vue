<template>
	<Fragment>
		<NcTextField :value.sync="conversationNameTextField"
			:label="t('spreed', 'Conversation Name')"
			:disabled="!isEditingDetails"
			:label-visible="true" />
		<NcTextField :value.sync="descriptionTextField"
			:label="t('spreed', 'Conversation description')"
			:placeholder="t('spreed', 'Enter a description for this conversation')"
			:disabled="!isEditingDetails"
			:label-visible="true" />
		<div class="details__buttons">
			<NcButton v-if="!isEditingDetails" type="secondary" @click="isEditingDetails = true">
				{{ t('spreed', 'Edit details') }}
			</NcButton>
			<template v-else-if="isEditingDetails">
				<NcButton type="tertiary" @click="cancelEditing">
					{{ t('spreed', 'Cancel') }}
				</NcButton>
				<NcButton type="secondary" @click="handleUpdateDetails">
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
	name: 'DetailsSettings',

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
			isEditingDetails: false,
			conversationNameTextField: '',
			descriptionTextField: '',
		}
	},

	watch: {
		// Update details everytime the conversation object changes
		conversation() {
			if (this.isEditingDetails) {
				return
			}
			this.updateDetailsLocalValues()
		},
	},

	mounted() {
		this.updateDetailsLocalValues()
	},

	methods: {
		updateDetailsLocalValues() {
			if (this.conversation.displayName !== this.conversationNameTextField) {
				this.conversationNameTextField = this.conversation.displayName

			}
			if (this.conversation.description !== this.descriptionTextField) {
				this.descriptionTextField = this.conversation.description ? this.conversation.description : ''
			}
		},

		cancelEditing() {
			this.updateDetailsLocalValues()
			this.isEditingDetails = false
		},

		async handleUpdateDetails() {
			this.isEditingDetails = true
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
			this.updateDetailsLocalValues()
			this.isEditingDetails = false
		},
	},
}
</script>

<style lang="scss" scoped>
.details {
	&__buttons {
		display: flex;
		gap: var(--default-grid-baseline);
		margin-top: calc(var(--default-grid-baseline) * 2);
	}
}
</style>
