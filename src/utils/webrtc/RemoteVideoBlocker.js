/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Helper to block the remote video when not needed.
 *
 * A remote video is not needed if the local user explicitly disabled it
 * (independently of whether the remote user (and thus owner of the remote
 * video) has it enabled or not) or if it is not visible.
 *
 * The remote video is not immediately hidden when no longer visible; a few
 * seconds are waited to avoid blocking and unblocking on layout changes.
 *
 * "increaseVisibleCounter()" can be called several times by the same view, but
 * "decreaseVisibleCounter()" must have been called a corresponding number of
 * times once the view is destroyed.
 *
 * A single RemoteVideoBlocker is assumed to be associated with its
 * CallParticipantModel, and it is also assumed to be the only element blocking
 * and unblocking the video. Otherwise the result is undefined.
 *
 * Note that the RemoteVideoBlocker can be used on participants that do not have
 * a video at all (for example, because they do not have a camera or they do not
 * have video permissions). In that case the CallParticipantModel will block the
 * video if needed if it becomes available.
 *
 * Once the CallParticipantModel is no longer used the associated
 * RemoteVideoBlocker must be destroyed to ensure that the video will not be
 * blocked or unblocked; calling any (modifier) method on the RemoteVideoBlocker
 * once destroyed has no effect.
 *
 * @param {object} callParticipantModel the model to block/unblock the video on.
 */
export default function RemoteVideoBlocker(callParticipantModel) {
	this._model = callParticipantModel

	// Keep track of the blocked state here, as the Peer object may not block
	// the video if some features are missing, and even if the video is blocked
	// the attribute will not be updated right away but once the renegotiation
	// is done.
	this._blocked = false

	this._enabled = true
	this._visibleCounter = 1

	this._blockVideoTimeout = null

	// Block by default if not shown after creation.
	this.decreaseVisibleCounter()
}

RemoteVideoBlocker.prototype = {

	destroy() {
		this._destroyed = true

		clearTimeout(this._blockVideoTimeout)
	},

	isVideoEnabled() {
		return this._enabled
	},

	setVideoEnabled(enabled) {
		if (this._destroyed) {
			return
		}

		this._enabled = enabled

		const hadBlockVideoTimeout = this._blockVideoTimeout

		clearTimeout(this._blockVideoTimeout)
		this._blockVideoTimeout = null

		if (!this._visibleCounter && !hadBlockVideoTimeout) {
			return
		}

		this._setVideoBlocked(!enabled)
	},

	increaseVisibleCounter() {
		if (this._destroyed) {
			return
		}

		this._visibleCounter++

		clearTimeout(this._blockVideoTimeout)
		this._blockVideoTimeout = null

		if (!this._enabled) {
			return
		}

		this._setVideoBlocked(false)
	},

	decreaseVisibleCounter() {
		if (this._destroyed) {
			return
		}

		if (this._visibleCounter <= 0) {
			console.error('Visible counter decreased when not visible')

			return
		}

		this._visibleCounter--

		if (this._visibleCounter > 0 || !this._enabled) {
			return
		}

		clearTimeout(this._blockVideoTimeout)

		this._blockVideoTimeout = setTimeout(() => {
			this._setVideoBlocked(true)

			this._blockVideoTimeout = null
		}, 5000)
	},

	_setVideoBlocked(blocked) {
		if (this._blocked === blocked) {
			return
		}

		this._blocked = blocked

		this._model.setVideoBlocked(blocked)
	},

}
