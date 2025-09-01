<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import TalkDashboard from '../components/Dashboard/TalkDashboard.vue'
import EmptyView from '../components/EmptyView.vue'
import IconTalk from '../../img/app-dark.svg?raw'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'

const supportsTalkDashboard = hasTalkFeature('local', 'dashboard-event-rooms')

const store = useStore()
const router = useRouter()
const route = useRoute()

const isCreatingConversationForCallUser = ref(false)
const callUser = computed(() => route.query.callUser as string)

const text = computed(() => {
	if (isCreatingConversationForCallUser.value) {
		return {
			name: t('spreed', 'Creating and joining a conversation with "{userid}"', { userid: callUser.value ?? '' }),
			description: '',
		}
	}

	return {
		name: t('spreed', 'Join a conversation or start a new one'),
		description: t('spreed', 'Say hi to your friends and colleagues!'),
	}
})

watchEffect(async () => {
	if (callUser.value) {
		try {
			// Try to find an existing conversation
			const conversation = store.getters.getConversationForUser(callUser.value)
			if (conversation) {
				router.push({ name: 'conversation', params: { token: conversation.token } })
				return
			}

			// Create a new one-to-one conversation
			isCreatingConversationForCallUser.value = true
			const newConversation = await store.dispatch('createOneToOneConversation', callUser.value)
			router.push({ name: 'conversation', params: { token: newConversation.token } })
		} catch (error) {
			showError(t('spreed', 'Error while joining the conversation'))
			console.error(error)
			router.push({ name: 'notfound' })
		}

		isCreatingConversationForCallUser.value = false
	}
})
</script>

<template>
	<TalkDashboard v-if="supportsTalkDashboard" />
	<EmptyView
		v-else
		:name="text.name"
		:description="text.description">
		<template #icon>
			<NcLoadingIcon v-if="isCreatingConversationForCallUser" />
			<NcIconSvgWrapper v-else :svg="IconTalk" />
		</template>
	</EmptyView>
</template>
