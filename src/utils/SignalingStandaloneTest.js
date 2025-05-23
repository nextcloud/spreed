/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateOcsUrl } from '@nextcloud/router'

/**
 * This is a simplified version of Signaling prototype (see signaling.js)
 * to be used for connection testing purposes (welcome, hello):
 * - no internal signaling supported
 * - no room events (join, leave, update) supported
 */

class StandaloneTest {
	constructor(settings, url) {
		this.settings = settings
		this.features = null
		this.version = null

		this.socket = null
		this.connected = false
		this.url = this.getWebSocketUrl(url)

		this.waitForWelcomeTimeout = null
		this.welcomeTimeoutMs = 3000
	}

	hasFeature(feature) {
		return this.features && this.features.includes(feature)
	}

	getWebSocketUrl(url) {
		return url
			.replace(/^http/, 'ws') // FIXME: not needed? request should be automatically upgraded to wss
			.replace(/\/$/, '') + '/spreed'
	}

	getBackendUrl(baseURL = undefined) {
		return generateOcsUrl('apps/spreed/api/v3/signaling/backend', {}, { baseURL })
	}

	connect() {
		console.debug('Connecting to ' + this.url + ' with ' + this.settings)

		return new Promise((resolve, reject) => {
			this.socket = new WebSocket(this.url)

			this.socket.onopen = (event) => {
				console.debug('Connected to websocket', event)
				if (this.settings.helloAuthParams['2.0']) {
					this.waitForWelcomeTimeout = setTimeout(this.welcomeResponseTimeout.bind(this), this.welcomeTimeoutMs)
				} else {
					this.sendHello()
				}
			}

			this.socket.onerror = (event) => {
				console.error('Error on websocket', event)
				this.disconnect()
			}

			this.socket.onclose = (event) => {
				if (event.wasClean) {
					console.info('Connection closed cleanly:', event)
					resolve(true)
				} else {
					console.warn(`Closing code ${event.code}. See https://www.rfc-editor.org/rfc/rfc6455.html#section-7.4`)
					reject(event)
				}
				this.socket = null
			}

			this.socket.onmessage = (event) => {
				let data = event.data
				if (typeof (data) === 'string') {
					data = JSON.parse(data)
				}
				if (OC.debug) {
					console.debug('Received', data)
				}

				switch (data.type) {
					case 'welcome':
						this.welcomeResponseReceived(data)
						break
					case 'hello':
						this.helloResponseReceived(data)
						break
					case 'error':
						console.error('Received error', data)
						break
					default:
						console.debug('Ignore unexpected event', data)
						break
				}
			}
		})
	}

	disconnect() {
		if (this.socket) {
			this.sendBye()
			this.socket.close()
			this.socket = null
		}
	}

	welcomeResponseReceived(data) {
		console.debug('Welcome received', data)
		if (this.waitForWelcomeTimeout !== null) {
			clearTimeout(this.waitForWelcomeTimeout)
			this.waitForWelcomeTimeout = null
		}

		if (data.welcome && data.welcome.features) {
			this.features = data.welcome.features
			this.version = data.welcome.version
		}

		this.sendHello()
	}

	welcomeResponseTimeout() {
		console.warn('No welcome received, assuming old-style signaling server')
		this.sendHello()
	}

	sendHello() {
		const version = this.hasFeature('hello-v2') ? '2.0' : '1.0'

		const msg = {
			type: 'hello',
			hello: {
				version,
				auth: {
					url: this.getBackendUrl(),
					params: this.settings.helloAuthParams[version],
				},
			},
		}

		this.socket.send(JSON.stringify(msg))
	}

	helloResponseReceived(data) {
		console.debug('Hello response received', data)
		this.connected = true
		this.disconnect()
	}

	sendBye() {
		if (this.connected) {
			this.socket.send(JSON.stringify({ type: 'bye', bye: {} }))
		}
	}
}

/**
 * Returns test instance
 * @param {object} settings signaling settings
 * @param {string} url HPB server URL
 */
function createConnection(settings, url) {
	if (!settings) {
		console.error('Signaling settings are not given')
	}

	return new StandaloneTest(settings, url)
}

export { createConnection }
