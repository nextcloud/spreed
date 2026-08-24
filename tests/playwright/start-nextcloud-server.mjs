/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { configureNextcloud, startNextcloud, stopNextcloud, waitOnNextcloud } from '@nextcloud/e2e-test-server/docker'
import { execSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

async function start() {
	const appinfo = readFileSync('appinfo/info.xml', 'utf-8')
	const maxVersion = appinfo.match(/<nextcloud min-version="\d+" max-version="(\d+)"\s*\/>/)?.[1]

	let branch = 'master'
	if (maxVersion) {
		const refs = execSync('git ls-remote --refs', { encoding: 'utf-8' })
		const stableBranch = `stable${maxVersion}`
		branch = refs.includes(`refs/heads/${stableBranch}`) ? stableBranch : branch
	}

	return startNextcloud(branch, true, {
		exposePort: Number.parseInt(process.env.NEXTCLOUD_PORT ?? '8089', 10),
	})
}

async function stop() {
	process.stderr.write('Stopping Nextcloud server...\n')
	await stopNextcloud()
	process.exit(0)
}

process.on('SIGTERM', stop)
process.on('SIGINT', stop)

const ip = await start()
await waitOnNextcloud(ip)
await configureNextcloud(['spreed'])

while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
