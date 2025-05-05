/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type { getDashboardEventRoomsResponse } from '../types/index.ts'

/**
 * Get the list of rooms that have events
 *
 */
const getDashboardEventRooms = async function(): getDashboardEventRoomsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/dashboard/events'))
}

export {
	getDashboardEventRooms,
}
