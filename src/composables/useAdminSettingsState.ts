/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { createSharedComposable } from '@vueuse/core'
import { ref } from 'vue'

/**
 * Shared composable for state between admin settings sections
 */
export const useAdminSettingsState = createSharedComposable(function() {
	return {
		hasFeatureJoinFeatures: ref(false),
		hasSIPBridge: ref(!!loadState<string>('spreed', 'sip_bridge_shared_secret', '')),
	}
})
