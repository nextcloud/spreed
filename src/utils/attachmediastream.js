/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
 *
 * @author Grigorii Shartsev <me@shgk.me>
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
 */

// Fork of https://github.com/otalk/attachMediaStream/blob/master/attachmediastream.js
// Lint (migrated to ES6)
// Migrated to ESM

import adapter from 'webrtc-adapter'

/**
 *
 * @param stream
 * @param el
 * @param options
 */
export default function attachmediastream(stream, el, options) {
	let item
	let element = el
	const opts = {
		autoplay: true,
		mirror: false,
		muted: false,
		audio: false,
		disableContextMenu: false,
	}

	if (options) {
		for (item in options) {
			opts[item] = options[item]
		}
	}

	if (!element) {
		element = document.createElement(opts.audio ? 'audio' : 'video')
	} else if (element.tagName.toLowerCase() === 'audio') {
		opts.audio = true
	}

	if (opts.disableContextMenu) {
		element.oncontextmenu = function(e) {
			e.preventDefault()
		}
	}

	if (opts.autoplay) element.autoplay = 'autoplay'
	element.muted = !!opts.muted
	if (!opts.audio) {
		['', 'moz', 'webkit', 'o', 'ms'].forEach(function(prefix) {
			const styleName = prefix ? prefix + 'Transform' : 'transform'
			element.style[styleName] = opts.mirror ? 'scaleX(-1)' : 'scaleX(1)'
		})
	}

	if (adapter.browserDetails.browser === 'safari') {
		element.setAttribute('playsinline', true)
	}

	element.srcObject = stream
	return element
}
