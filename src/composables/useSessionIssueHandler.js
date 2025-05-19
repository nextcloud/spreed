/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { nextTick, onBeforeMount, onBeforeUnmount, readonly, ref } from 'vue'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import { spawnDialog } from '@nextcloud/vue/functions/dialog'

import ConfirmDialog from '../components/UIShared/ConfirmDialog.vue'

import { useStore } from '../composables/useStore.js'
import { EventBus } from '../services/EventBus.ts'
import SessionStorage from '../services/SessionStorage.js'

/**
 * Check whether the conflicting session detected or not, and navigate to another page
 *
 * @return {import('vue').Ref<boolean>}
 */
export function useSessionIssueHandler() {
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

	const redirectTo = (url) => {
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

	const handleSessionConflict = (token) => {
		isLeavingAfterSessionIssue.value = true

		spawnDialog(ConfirmDialog, {
			name: t('spreed', 'Duplicate session'),
			message: t('spreed', 'You are trying to join a conversation while having an active session in another window or device. This is currently not supported by Nextcloud Talk. What do you want to do?'),
			buttons: [
				{
					label: t('spreed', 'Leave this page'),
				},
				{
					label: t('spreed', 'Join here'),
					type: 'primary',
					callback: () => {
						return true
					},
				}
			],
		}, (result) => {
			if (result) {
				isLeavingAfterSessionIssue.value = false
				store.dispatch('forceJoinConversation', { token })
			} else {
				duplicateSessionTriggered()
			}
		})
	}

	const duplicateSessionTriggered = () => {
		// TODO: DESKTOP: should close the duplicated window instead of redirect
		redirectTo('/apps/spreed/duplicate-session')
	}

	const deletedSessionTriggered = () => {
		// workaround: force page refresh to kill stray WebRTC connections
		redirectTo('/apps/spreed/not-found')
	}

	return readonly(isLeavingAfterSessionIssue)
}
