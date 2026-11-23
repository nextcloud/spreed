/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import IconBellMinusOutline from 'vue-material-design-icons/BellMinusOutline.vue'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconBellOutline from 'vue-material-design-icons/BellOutline.vue'
import IconBellRingOutline from 'vue-material-design-icons/BellRingOutline.vue'
import { PARTICIPANT } from '../../../constants.ts'
import { hasTalkFeature } from '../../../services/CapabilitiesManager.ts'

export const notificationLevelIcons = {
	[PARTICIPANT.NOTIFY.DEFAULT]: IconBellOutline,
	[PARTICIPANT.NOTIFY.ALWAYS]: IconBellRingOutline,
	[PARTICIPANT.NOTIFY.MENTION]: IconBellOutline,
	[PARTICIPANT.NOTIFY.NEVER]: IconBellOffOutline,
	[PARTICIPANT.NOTIFY.MENTION_DIRECT]: IconBellMinusOutline,
} as const

export const notificationLevels = [
	{ value: PARTICIPANT.NOTIFY.DEFAULT, label: t('spreed', 'Default'), description: t('spreed', 'Follow conversation settings') },
	{ value: PARTICIPANT.NOTIFY.ALWAYS, label: t('spreed', 'All messages'), description: undefined },
	{ value: PARTICIPANT.NOTIFY.MENTION, label: t('spreed', '@-mentions & @all'), description: undefined },
	{ value: PARTICIPANT.NOTIFY.MENTION_DIRECT, label: t('spreed', '@-mentions only'), description: undefined },
	{ value: PARTICIPANT.NOTIFY.NEVER, label: t('spreed', 'Off'), description: undefined },
] as const

/**
 * Get the thread notification levels supported by the server of the conversation
 *
 * @param token conversation token
 */
export function getNotificationLevels(token: string) {
	return hasTalkFeature(token, 'notify-direct-mentions')
		? notificationLevels
		: notificationLevels.filter((level) => level.value !== PARTICIPANT.NOTIFY.MENTION_DIRECT)
}
