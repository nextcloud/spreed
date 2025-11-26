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
 * Prompt user to start a floating call in place or navigate to Talk app to call there
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
		try {
			const userId = url.searchParams.get('callUser')!

			const conversationToken = (await createConversation({
				roomType: CONVERSATION.TYPE.ONE_TO_ONE,
				participants: { users: [userId] },
			})).data.ocs.data.token

			console.log(conversationToken)
		} catch (error) {
			console.error(error)
		}
	} else if (response === 'navigate') {
		// Navigate normally
		window.location.assign(url.href)
	} else {
		// dialog closed, no action needed
	}
}
