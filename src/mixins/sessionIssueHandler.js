/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
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

import { generateUrl } from '@nextcloud/router'
import { EventBus } from '../services/EventBus.js'
import SessionStorage from '../services/SessionStorage.js'

const sessionIssueHandler = {
	data() {
		return {
			isLeavingAfterSessionIssue: false,
		}
	},

	beforeDestroy() {
		EventBus.$off('duplicate-session-detected', this.duplicateSessionTriggered)
		EventBus.$off('deleted-session-detected', this.deletedSessionTriggered)
	},

	beforeMount() {
		EventBus.$on('duplicate-session-detected', this.duplicateSessionTriggered)
		EventBus.$on('deleted-session-detected', this.deletedSessionTriggered)
	},

	methods: {
		redirectTo(url) {
			this.isLeavingAfterSessionIssue = true
			SessionStorage.removeItem('joined_conversation')
			// Need to delay until next tick, otherwise the PreventUnload is still being triggered
			// Putting the window in front with the warning and irritating the user
			this.$nextTick(() => {
				// FIXME: can't use router push as it somehow doesn't clean up
				// fully and leads the other instance where "Join here" was clicked
				// to redirect to "not found"
				window.location = url
			})
		},

		duplicateSessionTriggered() {
			this.redirectTo(generateUrl('/apps/spreed/duplicate-session'))
		},

		deletedSessionTriggered() {
			// workaround: force page refresh to kill stray WebRTC connections
			this.redirectTo(generateUrl('/apps/spreed/not-found'))
		},
	},
}

export default sessionIssueHandler
