/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconBellOutline from 'vue-material-design-icons/BellOutline.vue'
import IconBellRingOutline from 'vue-material-design-icons/BellRingOutline.vue'
import { PARTICIPANT } from '../../../constants.ts'

export const notificationLevelIcons = {
	[PARTICIPANT.NOTIFY.DEFAULT]: IconBellOutline,
	[PARTICIPANT.NOTIFY.ALWAYS]: IconBellRingOutline,
	[PARTICIPANT.NOTIFY.MENTION]: IconBellOutline,
	[PARTICIPANT.NOTIFY.NEVER]: IconBellOffOutline,
} as const

export const notificationLevels = [
	{ value: PARTICIPANT.NOTIFY.DEFAULT, label: t('spreed', 'Default'), description: t('spreed', 'Follow conversation settings') },
	{ value: PARTICIPANT.NOTIFY.ALWAYS, label: t('spreed', 'All messages'), description: undefined },
	{ value: PARTICIPANT.NOTIFY.MENTION, label: t('spreed', '@-mentions only'), description: undefined },
	{ value: PARTICIPANT.NOTIFY.NEVER, label: t('spreed', 'Off'), description: undefined },
] as const
