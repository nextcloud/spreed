<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license GNU AGPL version 3 or any later version
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
	<Description :editable="canFullModerate"
		:description="description"
		:editing="isEditingDescription"
		:loading="isDescriptionLoading"
		:placeholder="t('spreed', 'Enter a description for this conversation')"
		@submit-description="handleUpdateDescription"
		@update:editing="handleEditDescription" />
</template>

<script>
import { showError } from '@nextcloud/dialogs'

import Description from '../Description/Description.vue'

export default {
	name: 'BasicInfo',

	components: {
		Description,
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

	data() {
		return {
			isEditingDescription: false,
			isDescriptionLoading: false,
		}
	},

	computed: {
		description() {
			return this.conversation.description
		},

		token() {
			return this.conversation.token
		},
	},

	methods: {
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

<style lang="scss" scoped>

</style>
