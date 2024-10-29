/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { nextTick, onBeforeMount, onBeforeUnmount, ref } from 'vue'

import { generateUrl } from '@nextcloud/router'

import { EventBus } from '../services/EventBus.ts'
import SessionStorage from '../services/SessionStorage.js'

/**
 * Check whether the conflicting session detected or not, and navigate to another page
 *
 * @return {import('vue').Ref<boolean>}
 */
export function useSessionIssueHandler() {
	const isLeavingAfterSessionIssue = ref(false)

	onBeforeMount(() => {
		EventBus.on('duplicate-session-detected', duplicateSessionTriggered)
		EventBus.on('deleted-session-detected', deletedSessionTriggered)
	})

	onBeforeUnmount(() => {
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
				window.location = generateUrl(url)
			})
		} else {
			window.location.hash = `#${url}`
			window.location.reload()
		}
	}

	const duplicateSessionTriggered = () => {
		// TODO: DESKTOP: should close the duplicated window instead of redirect
		redirectTo('/apps/spreed/duplicate-session')
	}

	const deletedSessionTriggered = () => {
		// workaround: force page refresh to kill stray WebRTC connections
		redirectTo('/apps/spreed/not-found')
	}

	return isLeavingAfterSessionIssue
}
