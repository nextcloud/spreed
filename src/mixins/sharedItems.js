/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { SHARED_ITEM } from '../constants.js'

const sharedItems = {
	computed: {
		// Defines the order of the sections
		sharedItemsOrder() {
			return [SHARED_ITEM.TYPES.MEDIA, SHARED_ITEM.TYPES.FILE, SHARED_ITEM.TYPES.RECORD_AUDIO, SHARED_ITEM.TYPES.RECORD_VIDEO, SHARED_ITEM.TYPES.POLL, SHARED_ITEM.TYPES.VOICE, SHARED_ITEM.TYPES.AUDIO, SHARED_ITEM.TYPES.LOCATION, SHARED_ITEM.TYPES.DECK_CARD, SHARED_ITEM.TYPES.OTHER]
		},
	},

	methods: {
		getTitle(type) {
			switch (type) {
			case SHARED_ITEM.TYPES.MEDIA:
				return t('spreed', 'Media')
			case SHARED_ITEM.TYPES.FILE:
				return t('spreed', 'Files')
			case SHARED_ITEM.TYPES.POLL:
				return t('spreed', 'Polls')
			case SHARED_ITEM.TYPES.DECK_CARD:
				return t('spreed', 'Deck cards')
			case SHARED_ITEM.TYPES.VOICE:
				return t('spreed', 'Voice messages')
			case SHARED_ITEM.TYPES.LOCATION:
				return t('spreed', 'Locations')
			case SHARED_ITEM.TYPES.RECORD_AUDIO:
			case SHARED_ITEM.TYPES.RECORD_VIDEO:
				return t('spreed', 'Call record')
			case SHARED_ITEM.TYPES.AUDIO:
				return t('spreed', 'Audio')
			default:
				return t('spreed', 'Other')
			}
		},
	},
}

export default sharedItems
