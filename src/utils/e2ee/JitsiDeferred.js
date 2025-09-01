/**
 * SPDX-FileCopyrightText: 2020 Jitsi team at 8x8 and the community.
 * SPDX-License-Identifier: Apache-2.0
 *
 * Based on code from https://github.com/jitsi/jitsi-meet
 */

/**
 * Promise-like object which can be passed around for resolving it later. It
 * implements the "thenable" interface, so it can be used wherever a Promise
 * could be used.
 *
 * In addition a "reject on timeout" functionality is provided.
 */
export default class Deferred {
	/**
	 * Instantiates a Deferred object.
	 */
	constructor() {
		this.promise = new Promise((resolve, reject) => {
			this.resolve = (...args) => {
				this.clearRejectTimeout()
				resolve(...args)
			}
			this.reject = (...args) => {
				this.clearRejectTimeout()
				reject(...args)
			}
		})
		this.then = this.promise.then.bind(this.promise)
		this.catch = this.promise.catch.bind(this.promise)
	}

	/**
	 * Clears the reject timeout.
	 */
	clearRejectTimeout() {
		clearTimeout(this._timeout)
	}

	/**
	 * Rejects the promise after the given timeout.
	 *
	 * @param ms
	 */
	setRejectTimeout(ms) {
		this._timeout = setTimeout(() => {
			this.reject(new Error('timeout'))
		}, ms)
	}
}
