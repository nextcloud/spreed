/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t } from '@nextcloud/l10n'
import { SHARED_ITEM } from '../../../constants.ts'

export const sharedItemsOrder = [SHARED_ITEM.TYPES.MEDIA,
	SHARED_ITEM.TYPES.FILE,
	SHARED_ITEM.TYPES.RECORDING,
	SHARED_ITEM.TYPES.POLL,
	SHARED_ITEM.TYPES.VOICE,
	SHARED_ITEM.TYPES.AUDIO,
	SHARED_ITEM.TYPES.LOCATION,
	SHARED_ITEM.TYPES.DECK_CARD,
	SHARED_ITEM.TYPES.OTHER]

export const sharedItemsWithPreviewLimit = [SHARED_ITEM.TYPES.DECK_CARD, SHARED_ITEM.TYPES.LOCATION, SHARED_ITEM.TYPES.POLL]

export const sharedItemTitle = {
	[SHARED_ITEM.TYPES.MEDIA]: t('spreed', 'Media'),
	[SHARED_ITEM.TYPES.FILE]: t('spreed', 'Files'),
	[SHARED_ITEM.TYPES.POLL]: t('spreed', 'Polls'),
	[SHARED_ITEM.TYPES.DECK_CARD]: t('spreed', 'Deck cards'),
	[SHARED_ITEM.TYPES.VOICE]: t('spreed', 'Voice messages'),
	[SHARED_ITEM.TYPES.LOCATION]: t('spreed', 'Locations'),
	[SHARED_ITEM.TYPES.RECORDING]: t('spreed', 'Call recordings'),
	[SHARED_ITEM.TYPES.AUDIO]: t('spreed', 'Audio'),
	[SHARED_ITEM.TYPES.OTHER]: t('spreed', 'Other'),
	default: t('spreed', 'Other'),
}

export const sharedItemButtonTitle = {
	[SHARED_ITEM.TYPES.MEDIA]: t('spreed', 'Show all media'),
	[SHARED_ITEM.TYPES.FILE]: t('spreed', 'Show all files'),
	[SHARED_ITEM.TYPES.POLL]: t('spreed', 'Show all polls'),
	[SHARED_ITEM.TYPES.DECK_CARD]: t('spreed', 'Show all deck cards'),
	[SHARED_ITEM.TYPES.VOICE]: t('spreed', 'Show all voice messages'),
	[SHARED_ITEM.TYPES.LOCATION]: t('spreed', 'Show all locations'),
	[SHARED_ITEM.TYPES.RECORDING]: t('spreed', 'Show all call recordings'),
	[SHARED_ITEM.TYPES.AUDIO]: t('spreed', 'Show all audio'),
	[SHARED_ITEM.TYPES.OTHER]: t('spreed', 'Show all other'),
	default: t('spreed', 'Show all other'),
}
