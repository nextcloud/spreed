/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { getTeamsProbeResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Get teams (circles) for a current user
 */
const getTeams = async (): getTeamsProbeResponse => {
	return axios.get(generateOcsUrl('/apps/circles/probecircles'))
}

export {
	getTeams,
}
