/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DeepReadonly, Ref } from 'vue'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { nextTick, onBeforeMount, onBeforeUnmount, readonly, ref } from 'vue'
import { useStore } from 'vuex'
import ConfirmDialog from '../components/UIShared/ConfirmDialog.vue'
import { EventBus } from '../services/EventBus.ts'
import SessionStorage from '../services/SessionStorage.js'

/**
 * Check whether the conflicting session detected or not, and navigate to another page
 */
export function useSessionIssueHandler(): DeepReadonly<Ref<boolean>> {
	const store = useStore()

	const isLeavingAfterSessionIssue = ref(false)

	onBeforeMount(() => {
		EventBus.on('session-conflict-confirmation', handleSessionConflict)
		EventBus.on('duplicate-session-detected', duplicateSessionTriggered)
		EventBus.on('deleted-session-detected', deletedSessionTriggered)
	})

	onBeforeUnmount(() => {
		EventBus.off('session-conflict-confirmation', handleSessionConflict)
		EventBus.off('duplicate-session-detected', duplicateSessionTriggered)
		EventBus.off('deleted-session-detected', deletedSessionTriggered)
	})

	/**
	 * Reload page/app with the new URL
	 * @param url - new URL
	 */
	function redirectTo(url: string) {
		isLeavingAfterSessionIssue.value = true
		SessionStorage.removeItem('joined_conversation')
		// Need to delay until next tick, otherwise the PreventUnload is still being triggered,
		// placing the warning window in the foreground and annoying the user
		if (!IS_DESKTOP) {
			nextTick(() => {
				// FIXME: can't use router push as it somehow doesn't clean up
				// fully and leads the other instance where "Join here" was clicked
				// to redirect to "not found"
				window.location.replace(generateUrl(url))
			})
		} else {
			window.location.hash = `#${url}`
			window.location.reload()
		}
	}

	/**
	 * Mark session conflict as pending and wait for user input to resolve
	 * Pending conflict should not send 'leave' requests to signaling server / webserver
	 * @param token - conversation token
	 */
	async function handleSessionConflict(token: string) {
		isLeavingAfterSessionIssue.value = true

		const result = await spawnDialog(ConfirmDialog, {
			name: t('spreed', 'Duplicate session'),
			message: t('spreed', 'You are trying to join a conversation while having an active session in another window or device. This is currently not supported by Nextcloud Talk. What do you want to do?'),
			buttons: [
				{
					label: t('spreed', 'Leave this page'),
					callback: () => undefined,
				},
				{
					label: t('spreed', 'Join here'),
					variant: 'primary',
					callback: () => true,
				},
			],
		})

		if (result) {
			isLeavingAfterSessionIssue.value = false
			store.dispatch('forceJoinConversation', { token })
		} else {
			duplicateSessionTriggered()
		}
	}

	/**
	 * Handle duplicate session
	 * TODO: DESKTOP: should close the duplicated window instead of redirect
	 */
	function duplicateSessionTriggered() {
		redirectTo('/apps/spreed/duplicate-session')
	}

	/**
	 * Handle deleted session
	 * TODO: current workaround is to force page refresh to kill stray WebRTC connections
	 */
	function deletedSessionTriggered() {
		redirectTo('/apps/spreed/not-found')
	}

	return readonly(isLeavingAfterSessionIssue)
}
