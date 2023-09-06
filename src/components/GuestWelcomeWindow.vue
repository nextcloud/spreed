<!--
  - @copyright Copyright (c) 2022, Dorra Jaouad <dorra.jaoued1@gmail.com>
  -
  - @author Dorra Jaouad <dorra.jaoued1@gmail.com>
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
  -
  -->

<template>
	<NcModal :container="container"
		:can-close="false"
		:close-on-click-outside="false"
		size="small">
		<div class="modal__content">
			<div class="conversation-information">
				<ConversationIcon :item="conversation"
					:show-user-status="false"
					disable-menu />
				<h2>{{ conversationDisplayName }}</h2>
			</div>
			<p class="description">
				{{ conversationDescription }}
			</p>

			<p class="input-label">
				{{ t('spreed', 'Enter your name') }}
			</p>

			<NcTextField :value.sync="guestUserName"
				:placeholder="t('spreed', 'Guest')"
				class="username-form__input"
				:show-trailing-button="false"
				@keydown.enter="handleChooseUserName" />

			<NcButton class="submit-button"
				type="primary"
				@click="handleChooseUserName">
				{{ submitMessage }}
				<template #icon>
					<Check :size="20" />
				</template>
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import Check from 'vue-material-design-icons/CheckBold.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import ConversationIcon from './ConversationIcon.vue'

export default {
	name: 'GuestWelcomeWindow',

	components: {
		NcModal,
		NcTextField,
		ConversationIcon,
		NcButton,
		Check,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			guestUserName: '',
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		conversationDisplayName() {
			return this.conversation?.displayName
		},

		conversationDescription() {
			return this.conversation?.description
		},

		submitMessage() {
			return t('spreed', 'Submit name and join')
		},
	},

	methods: {
		handleChooseUserName() {
			this.$store.dispatch('SubmitUserName', {
				token: this.token,
				name: this.guestUserName,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding: calc(var(--default-grid-baseline) * 4);
	background-color: var(--color-main-background);
	margin: 0px 12px;
}

.conversation-information {
	margin-top: 5px;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.description {
	margin-bottom: 12px;
}

.username-form__input {
	margin-bottom: 20px;
}

.submit-button {
	margin: 0 auto;
}
</style>
