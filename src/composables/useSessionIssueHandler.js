/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { nextTick, onBeforeMount, onBeforeUnmount, ref } from 'vue'

import { generateUrl } from '@nextcloud/router'

import { EventBus } from '../services/EventBus.js'
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
