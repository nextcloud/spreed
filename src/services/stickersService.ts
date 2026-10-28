/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { getStickerPacksResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetch the sticker packs of the current user
 */
async function getStickerPacks(): getStickerPacksResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/stickers'))
}

export {
	getStickerPacks,
}
