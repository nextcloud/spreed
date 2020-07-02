/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license GNU AGPL version 3 or any later version
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
import { EventBus } from '../services/EventBus'
import SessionStorage from '../services/SessionStorage'

const talkHashCheck = {
	data() {
		return {
			isLeavingAfterSessionConflict: false,
		}
	},

	beforeDestroy() {
		EventBus.$off('duplicateSessionDetected', this.duplicateSessionTriggered)
	},

	beforeMount() {
		EventBus.$on('duplicateSessionDetected', this.duplicateSessionTriggered)
	},

	methods: {
		duplicateSessionTriggered() {
			this.isLeavingAfterSessionConflict = true
			SessionStorage.removeItem('joined_conversation')
			this.$nextTick(() => {
				// Need to delay until next tick, otherwise the PreventUnload is still being triggered
				// Putting the window in front with the warning and irritating the user
				window.location = generateUrl('/apps/spreed/duplicate-session')
			})
		},
	},
}

export default talkHashCheck
