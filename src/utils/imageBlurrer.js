/**
 *
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { generateFilePath } from '@nextcloud/router'

const worker = new Worker(generateFilePath('spreed', '', 'js/image-blurrer-worker.js'))

const pendingResults = {}
let pendingResultsNextId = 0

worker.onmessage = function(message) {
	const pendingResult = pendingResults[message.data.id]
	if (!pendingResult) {
		console.debug('No pending result for blurring image with id ' + message.data.id)

		return
	}

	pendingResult(message.data.blurredImageAsDataUrl)

	delete pendingResults[message.data.id]
}

function blurSync(image, width, height, blurRadius) {
	return new Promise((resolve, reject) => {
		const canvas = document.createElement('canvas')
		canvas.width = width
		canvas.height = height

		const context = canvas.getContext('2d')
		context.filter = `blur(${blurRadius}px)`
		context.drawImage(image, 0, 0, canvas.width, canvas.height)

		resolve(canvas.toDataURL())
	})
}

export default function blur(image, width, height, blurRadius) {
	if (typeof OffscreenCanvas === 'undefined') {
		return blurSync(image, width, height, blurRadius)
	}

	const id = pendingResultsNextId

	pendingResultsNextId++

	return new Promise((resolve, reject) => {
		pendingResults[id] = resolve

		worker.postMessage({
			id: id,
			image: image,
			width: width,
			height: height,
			blurRadius: blurRadius,
		})
	})
}
