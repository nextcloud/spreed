<template>
	<EmptyView :name="text.name"
		:description="text.description">
		<template #icon>
			<NcIconSvgWrapper v-if="!isOnCallUser" :svg="IconTalk" />
			<NcLoadingIcon v-else />
		</template>
	</EmptyView>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

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
			isOnCallUser: false,
		}
	},

	computed: {
		callUser() {
			return this.$route.query.callUser
		},

		text() {
			const texts = {
				welcome: {
					name: this.t('spreed', 'Join a conversation or start a new one'),
					description: this.t('spreed', 'Say hi to your friends and colleagues!'),
				},
				callUser: {
					name: this.t('spreed', 'Joining conversation with "{userid}"', { userid: this.callUser ?? 'admin@nextcloud' }),
					description: '',
				},
			}
			return this.isOnCallUser ? texts.callUser : texts.welcome
		}
	},

	watch: {
		callUser: {
			immediate: true,
			async handler() {
				console.debug('111', 'callUser', this.callUser)
				if (!this.callUser) {
					return
				}
				this.isOnCallUser = true
				try {
					await this.createAndJoinConversation(this.callUser)
				} catch (error) {
					showError(this.t('spreed', 'Error while joining the conversation'))
					console.error(error)
				}
				this.isOnCallUser = false
			}
		}
	},

	methods: {
		async createAndJoinConversation(userId) {
			// Try to find an existing conversation
			const conversation = this.$store.getters.getConversationForUser(userId)
			if (conversation) {
				this.$router.push({
					name: 'conversation',
					params: { token: conversation.token },
				})
				return
			}

			// Create a new one-to-one conversation
			const newConversation = await this.$store.dispatch('createOneToOneConversation', userId)
			this.$router.push({
				name: 'conversation',
				params: { token: newConversation.token },
			})
		},
	}
}
</script>
