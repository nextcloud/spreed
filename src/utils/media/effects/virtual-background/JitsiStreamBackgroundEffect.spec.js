/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import JitsiStreamBackgroundEffect from './JitsiStreamBackgroundEffect.js'

describe('JitsiStreamBackgroundEffect', () => {
	describe('getSourcePropertiesForDrawingBackgroundImage', () => {
		test.each([
			['landscape source and landscape destination, wider aspect ratio source, wider and higher source', [1200, 500], [300, 200], [225, 0], [750, 500]],
			['landscape source and landscape destination, wider aspect ratio source, wider source', [450, 150], [300, 200], [112.5, 0], [225, 150]],
			['landscape source and landscape destination, wider aspect ratio source, same width', [300, 100], [300, 200], [75, 0], [150, 100]],
			['landscape source and landscape destination, wider aspect ratio source, narrower source', [200, 50], [300, 200], [62.5, 0], [75, 50]],
			['landscape source and landscape destination, wider aspect ratio destination, wider and higher destination', [300, 200], [1200, 500], [0, 37.5], [300, 125]],
			['landscape source and landscape destination, wider aspect ratio destination, wider destination', [300, 200], [450, 150], [0, 50], [300, 100]],
			['landscape source and landscape destination, wider aspect ratio destination, same width', [300, 200], [300, 100], [0, 50], [300, 100]],
			['landscape source and landscape destination, wider aspect ratio destination, narrower destination', [300, 200], [200, 50], [0, 62.5], [300, 75]],
			['landscape source and portrait destination, wider and higher source', [1200, 500], [201, 300], [432.5, 0], [335, 500]],
			['landscape source and portrait destination, wider source', [450, 150], [200, 300], [175, 0], [100, 150]],
			['landscape source and portrait destination, same width', [200, 100.5], [200, 300], [66.5, 0], [67, 100.5]],
			['landscape source and portrait destination, narrower source', [150, 51], [200, 300], [58, 0], [34, 51]],
			['portrait source and landscape destination, wider and higher source', [501, 1200], [300, 200], [0, 433], [501, 334]],
			['portrait source and landscape destination, higher source', [150, 450], [300, 200], [0, 175], [150, 100]],
			['portrait source and landscape destination, same height', [99, 200], [300, 200], [0, 67], [99, 66]],
			['portrait source and landscape destination, shorter source', [51, 150], [300, 200], [0, 58], [51, 34]],
			['portrait source and portrait destination, higher aspect ratio source, wider and higher source', [500, 1200], [200, 300], [0, 225], [500, 750]],
			['portrait source and portrait destination, higher aspect ratio source, higher source', [150, 450], [200, 300], [0, 112.5], [150, 225]],
			['portrait source and portrait destination, higher aspect ratio source, same height', [100, 300], [200, 300], [0, 75], [100, 150]],
			['portrait source and portrait destination, higher aspect ratio source, shorter source', [50, 200], [200, 300], [0, 62.5], [50, 75]],
			['portrait source and portrait destination, higher aspect ratio destination, wider and higher destination', [200, 300], [500, 1200], [37.5, 0], [125, 300]],
			['portrait source and portrait destination, higher aspect ratio destination, higher destination', [200, 300], [150, 450], [50, 0], [100, 300]],
			['portrait source and portrait destination, higher aspect ratio destination, same height', [200, 300], [100, 300], [50, 0], [100, 300]],
			['portrait source and portrait destination, higher aspect ratio destination, shorter destination', [200, 300], [50, 200], [62.5, 0], [75, 300]],
			['invalid source width', [0, 200], [100, 50], [0, 0], [0, 200]],
			['invalid source height', [200, 0], [100, 50], [0, 0], [200, 0]],
			['invalid destination width', [100, 50], [0, 200], [0, 0], [100, 50]],
			['invalid destination height', [100, 50], [200, 0], [0, 0], [100, 50]],
		])('%s', (name, [sourceWidth, sourceHeight], [destinationWidth, destinationHeight], [expectedSourceX, expectedSourceY], [expectedSourceWidth, expectedSourceHeight]) => {
			let sourceX
			let sourceY

			[sourceX, sourceY, sourceWidth, sourceHeight] = JitsiStreamBackgroundEffect.getSourcePropertiesForDrawingBackgroundImage(sourceWidth, sourceHeight, destinationWidth, destinationHeight)

			expect(sourceX).toBe(expectedSourceX)
			expect(sourceY).toBe(expectedSourceY)
			expect(sourceWidth).toBe(expectedSourceWidth)
			expect(sourceHeight).toBe(expectedSourceHeight)
		})
	})
})
