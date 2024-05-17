<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<EmptyView :name="text.name"
		:description="text.description">
		<template #icon>
			<NcLoadingIcon v-if="callUser" />
			<NcIconSvgWrapper v-else :svg="IconTalk" />
		</template>
	</EmptyView>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import EmptyView from '../components/EmptyView.vue'

import IconTalk from '../../img/app-dark.svg?raw'

export default {
	name: 'WelcomeView',

	components: {
		EmptyView,
		NcIconSvgWrapper,
		NcLoadingIcon,
	},

	setup() {
		return {
			IconTalk,
		}
	},

	data() {
		return {
			isCreatingConversationForCallUser: false,
		}
	},

	computed: {
		callUser() {
			return this.$route.query.callUser
		},

		text() {
			if (this.isCreatingConversationForCallUser) {
				return {
					name: t('spreed', 'Creating and joining a conversation with "{userid}"', { userid: this.callUser }),
					description: '',
				}
			}

			if (this.callUser) {
				return {
					name: t('spreed', 'Joining a conversation with "{userid}"', { userid: this.callUser }),
					description: '',
				}
			}

			return {
				name: t('spreed', 'Join a conversation or start a new one'),
				description: t('spreed', 'Say hi to your friends and colleagues!'),
			}
		}
	},

	watch: {
		callUser: {
			immediate: true,
			handler() {
				if (this.callUser) {
					this.createAndJoinConversationForCallUser()
				}
			}
		}
	},

	methods: {
		t,
		async createAndJoinConversationForCallUser() {
			// Try to find an existing conversation
			const conversation = this.$store.getters.getConversationForUser(this.callUser)
			if (conversation) {
				this.$router.push({
					name: 'conversation',
					params: { token: conversation.token },
				})
				return
			}

			// Create a new one-to-one conversation
			this.isCreatingConversationForCallUser = true
			try {
				const newConversation = await this.$store.dispatch('createOneToOneConversation', this.callUser)
				this.$router.push({
					name: 'conversation',
					params: { token: newConversation.token },
				})
			} catch (error) {
				showError(t('spreed', 'Error while joining the conversation'))
				console.error(error)
				this.$router.push({ name: 'notfound' })
			}
		},
	}
}
</script>
