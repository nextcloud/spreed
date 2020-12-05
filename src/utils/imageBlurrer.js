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

export default function blur(image, width, height, blurRadius) {
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
