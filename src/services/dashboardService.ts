/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { getDashboardEventRoomsResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Get the list of rooms that have events
 *
 */
async function getDashboardEventRooms(): getDashboardEventRoomsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/dashboard/events'))
}

export {
	getDashboardEventRooms,
}
