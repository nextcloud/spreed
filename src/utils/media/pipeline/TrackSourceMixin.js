/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Mixin for source nodes of tracks.
 *
 * For the base class refer to TrackSource instead.
 *
 * A media pipeline is a directed graph with nodes that act as a source, a
 * sink or both.
 *
 * A source node provides one or more output slots, and the output slot of a
 * source can be connected to several input slots of a sink. When the track in
 * an output slot changes the connected input slots of the sinks are updated.
 * Each slot is identified with a unique id. By default, an id named "default"
 * is used.
 *
 * An output slot can be connected to the input slot of a sink with
 * "connectTrackSink(outputTrackId, trackSink, inputTrackId)" and disconnected
 * with "disconnectTrackSink(outputTrackId, trackSink, inputTrackId)". The
 * current track in an output slot can be got with "getOutputTrack(trackId)".
 *
 * The following events are emitted by a source node:
 * - "outputTrackSet", with "trackId" and "track" parameters
 * - "outputTrackEnabled", with "trackId" and "enabled" parameters
 *
 * Classes applying this mixin are expected to also apply the EmitterMixin.
 *
 * The mixin can be inherited calling
 * "TrackSourceMixin.apply(Inheriter.prototype)"; "_superTrackSourceMixin()"
 * must be called from the constructor (but only from the class directly
 * inheriting the mixin, not from subclasses).
 *
 * Inheriters of the mixin should call "_addOutputTrackSlot(trackId)" to define
 * their output slots. If needed (for example, to define the slots dynamically),
 * an output slot can be removed too with "_removeOutputTrackSlot(trackId)".
 *
 * Inheriters of the mixin should call the "_setOuputTrack(trackId, track)" and
 * "_setOutputTrackEnabled(trackId, enabled)" methods as needed. The connected
 * sinks will be automatically updated when those methods are called.
 *
 * Note that ended tracks are automatically removed; the output track does not
 * need to be automatically set to null in that case. If needed, this can be
 * prevented by calling "_disableRemoveTrackWhenEnded(track)". However, removing
 * a track does not automatically stop it; that needs to be explicitly done if
 * needed.
 */
export default (function() {
	/**
	 * Mixin constructor.
	 *
	 * Adds mixin attributes to objects inheriting the mixin.
	 *
	 * This must be called in their constructor by classes inheriting the mixin.
	 */
	function _superTrackSourceMixin() {
		this._outputTracks = {}
		this._connectedTrackSinks = {}

		this._removeTrackWhenEndedHandlers = {}
	}

	/**
	 * @param {string|number} outputTrackId the id of the output track in the
	 *        source
	 * @param {object} trackSink the TrackSink to connect this source to
	 * @param {string|number} inputTrackId the id of the input track in the sink
	 */
	function connectTrackSink(outputTrackId, trackSink, inputTrackId = 'default') {
		trackSink.connectTrackSource(inputTrackId, this, outputTrackId)
	}

	/**
	 * @param {string|number} outputTrackId the id of the output track in the
	 *        source
	 * @param {object} trackSink the TrackSink to disconnect this sink from
	 * @param {string|number} inputTrackId the id of the input track in the sink
	 */
	function disconnectTrackSink(outputTrackId, trackSink, inputTrackId = 'default') {
		trackSink.disconnectTrackSource(inputTrackId, this, outputTrackId)
	}

	/**
	 * @param {string|number} trackId the id of the output track to get
	 */
	function getOutputTrack(trackId = 'default') {
		if (!Object.prototype.hasOwnProperty.call(this._outputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		return this._outputTracks[trackId]
	}

	/**
	 * @param {string|number} trackId the id of the output track slot to add
	 */
	function _addOutputTrackSlot(trackId = 'default') {
		if (Object.prototype.hasOwnProperty.call(this._outputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		this._outputTracks[trackId] = null
	}

	/**
	 * @param {string|number} trackId the id of the output track slot to remove
	 */
	function _removeOutputTrackSlot(trackId = 'default') {
		if (!Object.prototype.hasOwnProperty.call(this._outputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		delete this._outputTracks[trackId]
	}

	/**
	 * Sets an output track.
	 *
	 * The connected sinks will automatically react to the change and update
	 * their input tracks.
	 *
	 * Inheriters should call this method to set their output tracks.
	 *
	 * When an inheriter applies constraints to an output track this method has
	 * to be called again with the same output track.
	 *
	 * @param {string|number} trackId the id of the output track to set
	 * @param {MediaStreamTrack|null} track the track to set
	 */
	function _setOutputTrack(trackId, track) {
		if (!Object.prototype.hasOwnProperty.call(this._outputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		// "ended" listener is not removed if the same track is also used in a
		// different slot.
		if (this._outputTracks[trackId] && Object.values(this._outputTracks).filter((track) => track === this._outputTracks[trackId]).length === 1) {
			this._outputTracks[trackId].removeEventListener('ended', this._removeTrackWhenEndedHandlers[this._outputTracks[trackId].id])
		}

		this._outputTracks[trackId] = track

		// "ended" listener is not added again if the same track is also used in
		// a different slot.
		if (this._outputTracks[trackId] && Object.values(this._outputTracks).filter((track) => track === this._outputTracks[trackId]).length === 1) {
			// The "ended" event may not contain the track that ended (for
			// example, when triggered from the MediaStreamTrack shim, as
			// properties like "target" can not be set from the Event
			// constructor), so it needs to be explicitly bound here.
			this._removeTrackWhenEndedHandlers[track.id] = () => {
				this._removeTrackWhenEnded(track)
			}
			this._outputTracks[trackId].addEventListener('ended', this._removeTrackWhenEndedHandlers[track.id])
		}

		this._trigger('outputTrackSet', [trackId, track])
	}

	/**
	 * @param {MediaStreamTrack} track the track to prevent being automatically
	 *        removed when ended
	 */
	function _disableRemoveTrackWhenEnded(track) {
		const trackIds = Object.keys(this._outputTracks)

		trackIds.forEach((trackId) => {
			if (this._outputTracks[trackId] === track) {
				this._outputTracks[trackId].removeEventListener('ended', this._removeTrackWhenEndedHandlers[this._outputTracks[trackId].id])
			}
		})
	}

	/**
	 * @param {MediaStreamTrack} track the ended track to remove
	 */
	function _removeTrackWhenEnded(track) {
		const trackIds = Object.keys(this._outputTracks)

		trackIds.forEach((trackId) => {
			if (this._outputTracks[trackId] === track) {
				this._setOutputTrack(trackId, null)
			}
		})
	}

	/**
	 * Enables or disables an output track.
	 *
	 * The connected sinks will automatically react to the change and update
	 * their input tracks.
	 *
	 * Inheriters should call this method to enable or disable their output
	 * tracks. The "enabled" property of the track should not be directly
	 * modified.
	 *
	 * @param {string|number} trackId the id of the output track to set its
	 *        enabled state
	 * @param {boolean} enabled the enabled state of the output track
	 */
	function _setOutputTrackEnabled(trackId, enabled) {
		if (!Object.prototype.hasOwnProperty.call(this._outputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		if (!this._outputTracks[trackId]) {
			return
		}

		this._outputTracks[trackId].enabled = enabled

		this._trigger('outputTrackEnabled', [trackId, enabled])
	}

	return function() {
		// Add methods to the prototype from the functions defined above, but
		// only if they are not overriden. Overriden methods are fully
		// overriden, so they can not call the parent implementation.
		this._superTrackSourceMixin = this._superTrackSourceMixin || _superTrackSourceMixin
		this.connectTrackSink = this.connectTrackSink || connectTrackSink
		this.disconnectTrackSink = this.disconnectTrackSink || disconnectTrackSink
		this.getOutputTrack = this.getOutputTrack || getOutputTrack
		this._addOutputTrackSlot = this._addOutputTrackSlot || _addOutputTrackSlot
		this._removeOutputTrackSlot = this._removeOutputTrackSlot || _removeOutputTrackSlot
		this._setOutputTrack = this._setOutputTrack || _setOutputTrack
		this._disableRemoveTrackWhenEnded = this._disableRemoveTrackWhenEnded || _disableRemoveTrackWhenEnded
		this._removeTrackWhenEnded = this._removeTrackWhenEnded || _removeTrackWhenEnded
		this._setOutputTrackEnabled = this._setOutputTrackEnabled || _setOutputTrackEnabled
	}
})()
