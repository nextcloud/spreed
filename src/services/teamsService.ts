/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { getTeamFolderResponse, getTeamsProbeResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Get teams (circles) for a current user
 */
async function getTeams(): getTeamsProbeResponse {
	return axios.get(generateOcsUrl('/apps/circles/probecircles'))
}

/**
 * Get the folder of a team, relative to the files of the current user
 *
 * @param teamId - the team id
 */
async function getTeamFolder(teamId: string): getTeamFolderResponse {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/team/{teamId}/folder', { teamId }))
}

export {
	getTeamFolder,
	getTeams,
}
