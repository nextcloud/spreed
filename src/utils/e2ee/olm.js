/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Olm from '@matrix-org/olm'
import wasmFile from '@matrix-org/olm/olm.wasm'
import { generateFilePath } from '@nextcloud/router'

let initialized = false

/**
 * Initializes the Olm library that is used for e2e encryption.
 */
async function initialize() {
	if (initialized) {
		return
	}

	await Olm.init({
		locateFile: () => {
			if (IS_DESKTOP) {
				return wasmFile
			}
			// FIXME this is a dirty hack and should be properly fixed so it does not break when changing the webpack config in a unknown way.
			return generateFilePath('spreed', 'js', wasmFile.split('/').pop())
		},
	})
	initialized = true
	console.debug('Initialized Olm version', Olm.get_library_version().join('.'))
}

export default initialize
