/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import SegmenterCore from './SegmenterCore.js'

const core = new SegmenterCore()

self.onmessage = async ({ data }) => {
	switch (data.type) {
		case 'init': {
			try {
				await core.init(data.assets)
				self.postMessage({ type: 'initDone' })
			} catch (error) {
				self.postMessage({ type: 'initError', message: error?.message ?? String(error) })
			}
			break
		}
		case 'segment': {
			try {
				const mask = await core.segment(data.frame, data.timestampMs)
				self.postMessage({ type: 'segmentDone', mask }, mask ? [mask.data.buffer] : [])
			} catch (error) {
				// core.segment() closes the frame in its finally; this is a defensive no-op double-close
				data.frame?.close?.()
				self.postMessage({ type: 'segmentError', message: error?.message ?? String(error) })
			}
			break
		}
		case 'close': {
			core.close()
			self.close()
			break
		}
	}
}
