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

const fileReaderSync = new global.FileReaderSync()

onmessage = function(message) {
	const offscreenCanvas = new OffscreenCanvas(message.data.width, message.data.height)

	const context = offscreenCanvas.getContext('2d')
	context.filter = `blur(${message.data.blurRadius}px)`
	context.drawImage(message.data.image, 0, 0, offscreenCanvas.width, offscreenCanvas.height)

	offscreenCanvas.convertToBlob().then(blob => {
		postMessage({
			id: message.data.id,
			blurredImageAsDataUrl: fileReaderSync.readAsDataURL(blob),
		})
	})
}
