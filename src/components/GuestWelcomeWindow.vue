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
	<NcModal v-if="showModal"
		:container="container"
		:can-close="false"
		:close-on-click-outside="false"
		size="small">
		<div class="modal__content">
			<div class="conversation-information">
				<ConversationIcon :item="conversation"
					:show-user-status="false"
					:disable-menu="true" />
				<h2>{{ conversationDisplayName }}</h2>
			</div>
			<h3 class="description">
				{{ conversationDescription }}
			</h3>

			<h3 class="input-label">
				{{ t('spreed', 'Enter your name') }}
			</h3>
			<SetGuestUsername ref="setGuestUsername"
				class="guest-user-form"
				first-welcome
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

import ConversationIcon from './ConversationIcon.vue'
import SetGuestUsername from './SetGuestUsername.vue'

export default {
	name: 'GuestWelcomeWindow',

	components: {
		NcModal,
		SetGuestUsername,
		ConversationIcon,
		NcButton,
		Check,
	},

	data() {
		return {
			showModal: true,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		conversationDisplayName() {
			return this.conversation && this.conversation.displayName
		},

		conversationDescription() {
			return this.conversation && this.conversation.description
		},

		submitMessage() {
			return t('spreed', 'Submit name and join')
		},
	},

	methods: {
		handleChooseUserName() {
			this.$refs.setGuestUsername.handleChooseUserName()
			this.showModal = false
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding: calc(var(--default-grid-baseline) * 4);
	background-color: var(--color-main-background);

	:deep(.username-form__input) {
		width: auto !important;
	}
}

.conversation-information {
	margin-top: 5px;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.description {
	margin: 0px 12px 12px 12px;
}

.input-label {
	margin: 0px 12px;
}

.submit-button {
	margin: 0 auto;
}
</style>
