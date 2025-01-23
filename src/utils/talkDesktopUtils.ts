/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

export const messagePleaseReload = IS_DESKTOP
	? t('spreed', 'Please restart the app.')
	: t('spreed', 'Please reload the page.')

export const messagePleaseTryToReload = IS_DESKTOP
	? t('spreed', 'Please try to restart the app.')
	: t('spreed', 'Please try to reload the page.')
