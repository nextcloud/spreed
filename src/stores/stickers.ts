/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { StickerPack } from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getStickerPacks } from '../services/stickersService.ts'

export const useStickersStore = defineStore('stickers', () => {
	const packs = ref<StickerPack[]>([])
	const loaded = ref(false)
	const loading = ref(false)

	/**
	 * Fetch the sticker packs of the current user. Packs fetched before stay
	 * shown meanwhile, so the picker opens without waiting.
	 */
	async function fetchPacks() {
		if (loading.value) {
			return
		}
		loading.value = true
		try {
			const response = await getStickerPacks()
			packs.value = response.data.ocs.data
			loaded.value = true
		} catch (error) {
			console.error('Failed to fetch sticker packs:', error)
			showError(t('spreed', 'Failed to load stickers'))
		} finally {
			loading.value = false
		}
	}

	return {
		packs,
		loaded,
		loading,
		fetchPacks,
	}
})
