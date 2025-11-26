/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import ConfirmDialog from './components/UIShared/ConfirmDialog.vue'
import { CONVERSATION } from './constants.ts'
import { createConversation } from './services/conversationsService.ts'

/**
 * Prompt the user to start a floating call in place or navigate to the app
 *
 * @param url Intercepted URL (<...>/apps/spreed/?callUser=<userId>)
 */
export async function handleInterceptedLink(url: URL) {
	const response = await spawnDialog(ConfirmDialog, {
		name: 'Thumbnail',
		buttons: [
			{
				label: t('spreed', 'Continue in Talk app'),
				callback: () => 'navigate',
			},
			{
				label: t('spreed', 'Call right now!'),
				variant: 'primary',
				callback: () => 'integration',
			},
		],
	})

	if (response === 'integration') {
		await handleStartFloatingCall(url.searchParams.get('callUser')!)
	} else if (response === 'navigate') {
		// Navigate normally
		window.location.assign(url.href)
	} else {
		// dialog closed, no action needed
	}
}

/**
 * Initializes the floating container on the page
 *
 * @param userId Intercepted URL (<...>/apps/spreed/?callUser=<userId>)
 */
export async function handleStartFloatingCall(userId: string) {
	// TODO
}
