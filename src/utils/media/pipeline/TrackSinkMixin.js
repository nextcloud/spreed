/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Mixin for sink nodes of tracks.
 *
 * For the base class refer to TrackSink instead.
 *
 * A media pipeline is a directed graph with nodes that act as a source, a
 * sink or both.
 *
 * A sink node provides one or more input slots, and the input slot of a sink
 * can be connected only to a single output slot of a source. When the track in
 * an output slot changes the connected input slots of the sinks are updated.
 * Each slot is identified with a unique id. By default, an id named "default"
 * is used.
 *
 * An input slot can be connected to the output slot of a source with
 * "connectTrackSource(inputTrackId, trackSource, outputTrackId)" and
 * disconnected with
 * "disconnectTrackSource(inputTrackId, trackSource, outputTrackId)". The
 * current track in an input slot can be got with "getInputTrack(trackId)".
 *
 * The mixin can be inherited calling
 * "TrackSinkMixin.apply(Inheriter.prototype)"; "_superTrackSinkMixin()" must be
 * called from the constructor (but only from the class directly inheriting the
 * mixin, not from subclasses).
 *
 * Inheriters of the mixin should call "_addInputTrackSlot(trackId)" to define
 * their input slots. If needed (for example, to define the slots dynamically),
 * an input slot can be removed too with "_removeInputTrackSlot(trackId)".
 *
 * Inheriters of the mixin should define the "_handleInputTrack(trackId,
 * newTrack, oldTrack)" and "_handleInputTrackEnabled(trackId, enabled)"
 * methods. Those methods are automatically called when the connected source
 * track changes. Note, however, that when a different source track is set the
 * "_handleInputTrackEnabled()" method will not be called even if the new track
 * has a different state than the previous track; it is expected that, if
 * needed, inheriters check its enabled state when an input track is set.
 */
export default (function() {
	/**
	 * Mixin constructor.
	 *
	 * Adds mixin attributes to objects inheriting the mixin.
	 *
	 * This must be called in their constructor by classes inheriting the mixin.
	 */
	function _superTrackSinkMixin() {
		this._inputTracks = []

		this._connectedTrackSources = []

		this._handleOutputTrackSetBound = this._handleOutputTrackSet.bind(this)
		this._handleOutputTrackEnabledBound = this._handleOutputTrackEnabled.bind(this)
	}

	/**
	 * @param {string|number} inputTrackId the id of the input track in the sink
	 * @param {object} trackSource the TrackSource to connect this sink to
	 * @param {string|number} outputTrackId the id of the output track in the
	 *        source
	 */
	function connectTrackSource(inputTrackId, trackSource, outputTrackId = 'default') {
		if (!Object.prototype.hasOwnProperty.call(this._inputTracks, inputTrackId)) {
			throw new Error('Invalid input track id: ' + inputTrackId)
		}

		const connectedTrackSource = this._connectedTrackSources.find(connected => connected.inputTrackId === inputTrackId)
		if (connectedTrackSource) {
			if (connectedTrackSource.trackSource !== trackSource || connectedTrackSource.outputTrackId !== outputTrackId) {
				throw new Error('Input track id is already connected to another source: ', inputTrackId, connectedTrackSource.trackSource, connectedTrackSource.outputTrackId)
			}

			return
		}

		if (this.getInputTrack(inputTrackId) !== trackSource.getOutputTrack(outputTrackId)) {
			this._setInputTrack(inputTrackId, trackSource.getOutputTrack(outputTrackId))
		}

		trackSource.on('outputTrackSet', this._handleOutputTrackSetBound)
		trackSource.on('outputTrackEnabled', this._handleOutputTrackEnabledBound)

		this._connectedTrackSources.push({
			trackSource,
			outputTrackId,
			inputTrackId,
		})
	}

	/**
	 * @param {string|number} inputTrackId the id of the input track in the sink
	 * @param {object} trackSource the TrackSource to disconnect this sink from
	 * @param {string|number} outputTrackId the id of the output track in the
	 *        source
	 */
	function disconnectTrackSource(inputTrackId, trackSource, outputTrackId = 'default') {
		const connectedTrackSourceIndex = this._connectedTrackSources.findIndex(connected =>
			connected.trackSource === trackSource
			&& connected.outputTrackId === outputTrackId
			&& connected.inputTrackId === inputTrackId)
		if (connectedTrackSourceIndex === -1) {
			return
		}

		this._connectedTrackSources.splice(connectedTrackSourceIndex, 1)

		trackSource.off('outputTrackSet', this._handleOutputTrackSetBound)
		trackSource.off('outputTrackEnabled', this._handleOutputTrackEnabledBound)

		if (this.getInputTrack(inputTrackId) !== null) {
			this._setInputTrack(inputTrackId, null)
		}
	}

	/**
	 * @param {string|number} trackId the id of the input track to get
	 */
	function getInputTrack(trackId = 'default') {
		if (!Object.prototype.hasOwnProperty.call(this._inputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		return this._inputTracks[trackId]
	}

	/**
	 * @param {string|number} trackId the id of the input slot to add
	 */
	function _addInputTrackSlot(trackId = 'default') {
		if (Object.prototype.hasOwnProperty.call(this._inputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		this._inputTracks[trackId] = null
	}

	/**
	 * @param {string|number} trackId the id of the input slot to remove
	 */
	function _removeInputTrackSlot(trackId = 'default') {
		if (!Object.prototype.hasOwnProperty.call(this._inputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		const connectedTrackSource = this._connectedTrackSources.find(connected => connected.inputTrackId === trackId)
		if (connectedTrackSource) {
			throw new Error('Connected input track slot can not be removed: ' + trackId)
		}

		delete this._inputTracks[trackId]
	}

	/**
	 * @param {object} trackSource the TrackSource where the track was set
	 * @param {string|number} outputTrackId the id of the output track in the
	 *        source
	 * @param {MediaStreamTrack|null} track the track set in the source
	 */
	function _handleOutputTrackSet(trackSource, outputTrackId, track) {
		this._connectedTrackSources.forEach(connected => {
			if (connected.trackSource === trackSource && connected.outputTrackId === outputTrackId) {
				this._setInputTrack(connected.inputTrackId, track)
			}
		})
	}

	/**
	 * @param {object} trackSource the TrackSource where the track was enabled
	 *        or disabled
	 * @param {string|number} outputTrackId the id of the output track in the
	 *        source
	 * @param {boolean} enabled whether the track was enabled or disabled
	 */
	function _handleOutputTrackEnabled(trackSource, outputTrackId, enabled) {
		this._connectedTrackSources.forEach(connected => {
			if (connected.trackSource === trackSource && connected.outputTrackId === outputTrackId) {
				this._setInputTrackEnabled(connected.inputTrackId, enabled)
			}
		})
	}

	/**
	 * @param {string|number} trackId the id of the input track to set
	 * @param {MediaStreamTrack|null} track the track to set
	 */
	function _setInputTrack(trackId, track) {
		if (!Object.prototype.hasOwnProperty.call(this._inputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		const oldTrack = this._inputTracks[trackId]

		this._inputTracks[trackId] = track

		this._handleInputTrack(trackId, track, oldTrack)
	}

	/**
	 * @param {string|number} trackId the id of the input track to set its
	 *        enabled state
	 * @param {boolean} enabled the enabled state of the input track
	 */
	function _setInputTrackEnabled(trackId, enabled) {
		if (!Object.prototype.hasOwnProperty.call(this._inputTracks, trackId)) {
			throw new Error('Invalid track id: ' + trackId)
		}

		if (!this._inputTracks[trackId]) {
			return
		}

		this._handleInputTrackEnabled(trackId, enabled)
	}

	/**
	 * Called when an input track is set.
	 *
	 * This method should be implemented by inheriters.
	 *
	 * @param {string|number} trackId the id of the input track to set
	 * @param {MediaStreamTrack|null} newTrack the new track to set
	 * @param {MediaStreamTrack|null} oldTrack the old track with the given id
	 */
	function _handleInputTrack(trackId, newTrack, oldTrack) {
	}

	/**
	 * Called when an input track is enabled or disabled.
	 *
	 * This method should be implemented by inheriters.
	 *
	 * @param {string|number} trackId the id of the input track to set its
	 *        enabled state
	 * @param {boolean} enabled the enabled state of the input track
	 */
	function _handleInputTrackEnabled(trackId, enabled) {
	}

	return function() {
		// Add methods to the prototype from the functions defined above, but
		// only if they are not overriden. Overriden methods are fully
		// overriden, so they can not call the parent implementation.
		this._superTrackSinkMixin = this._superTrackSinkMixin || _superTrackSinkMixin
		this.connectTrackSource = this.connectTrackSource || connectTrackSource
		this.disconnectTrackSource = this.disconnectTrackSource || disconnectTrackSource
		this.getInputTrack = this.getInputTrack || getInputTrack
		this._addInputTrackSlot = this._addInputTrackSlot || _addInputTrackSlot
		this._removeInputTrackSlot = this._removeInputTrackSlot || _removeInputTrackSlot
		this._handleOutputTrackSet = this._handleOutputTrackSet || _handleOutputTrackSet
		this._handleOutputTrackEnabled = this._handleOutputTrackEnabled || _handleOutputTrackEnabled
		this._setInputTrack = this._setInputTrack || _setInputTrack
		this._setInputTrackEnabled = this._setInputTrackEnabled || _setInputTrackEnabled
		this._handleInputTrack = this._handleInputTrack || _handleInputTrack
		this._handleInputTrackEnabled = this._handleInputTrackEnabled || _handleInputTrackEnabled
	}
})()
