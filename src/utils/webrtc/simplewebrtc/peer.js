/* global module */

import util from 'util'

import adapter from 'webrtc-adapter'
import webrtcSupport from 'webrtcsupport'
import WildEmitter from 'wildemitter'

/**
 * @param {object} stream the stream object.
 */
function isAllTracksEnded(stream) {
	let isAllTracksEnded = true
	stream.getTracks().forEach(function(t) {
		isAllTracksEnded = t.readyState === 'ended' && isAllTracksEnded
	})
	return isAllTracksEnded
}

/**
 * @param {object} options the options object.
 */
function Peer(options) {
	const self = this

	// call emitter constructor
	WildEmitter.call(this)

	this.id = options.id
	this.parent = options.parent
	this.type = options.type || 'video'
	this.oneway = options.oneway || false
	this.sharemyscreen = options.sharemyscreen || false
	this.stream = options.stream
	this.receiverOnly = options.receiverOnly
	this.sendVideoIfAvailable = options.sendVideoIfAvailable === undefined ? true : options.sendVideoIfAvailable
	this.enableDataChannels = options.enableDataChannels === undefined ? this.parent.config.enableDataChannels : options.enableDataChannels
	this.enableSimulcast = options.enableSimulcast === undefined ? this.parent.config.enableSimulcast : options.enableSimulcast
	this.maxBitrates = options.maxBitrates === undefined ? this.parent.config.maxBitrates : options.maxBitrates
	this.receiveMedia = options.receiveMedia || this.parent.config.receiveMedia
	this.channels = {}
	this.pendingDCMessages = [] // key (datachannel label) -> value (array[pending messages])
	this._pendingReplaceTracksQueue = []
	this._processPendingReplaceTracksPromise = null
	this._initialStreamSetup = false
	this.sid = options.sid || Date.now().toString()
	this.pc = new RTCPeerConnection(this.parent.config.peerConnectionConfig)
	this.pc.addEventListener('icecandidate', this.onIceCandidate.bind(this))
	this.pc.addEventListener('endofcandidates', function(event) {
		self.send('endOfCandidates', event)
	})
	this.pc.addEventListener('addstream', this.handleRemoteStreamAdded.bind(this))
	this.pc.addEventListener('datachannel', this.handleDataChannelAdded.bind(this))
	this.pc.addEventListener('removestream', this.handleStreamRemoved.bind(this))
	// Just fire negotiation needed events for now
	// When browser re-negotiation handling seems to work
	// we can use this as the trigger for starting the offer/answer process
	// automatically. We'll just leave it be for now while this stabalizes.
	this.pc.addEventListener('negotiationneeded', this.emit.bind(this, 'negotiationNeeded'))
	this.pc.addEventListener('iceconnectionstatechange', this.emit.bind(this, 'iceConnectionStateChange'))
	this.pc.addEventListener('iceconnectionstatechange', function() {
		if (!options.receiverOnly && self.pc.iceConnectionState !== 'new') {
			self._processPendingReplaceTracks().then(finished => {
				if (finished === false || self._initialStreamSetup) {
					return
				}

				// Ensure that initially disabled tracks are stopped after
				// establishing a connection.
				self.pc.getSenders().forEach(sender => {
					if (sender.track) {
						// The stream is not known, but it is only used when the
						// track is added, so it can be ignored here.
						self.handleSentTrackEnabledChanged(sender.track, null)
					}
				})

				self._initialStreamSetup = true
			})
		} else {
			self._initialStreamSetup = false
		}

		switch (self.pc.iceConnectionState) {
		case 'failed':
			// currently, in chrome only the initiator goes to failed
			// so we need to signal this to the peer
			if (self.pc.localDescription.type === 'offer') {
				self.parent.emit('iceFailed', self)
				self.send('connectivityError')
			}
			break
		}
	})
	this.pc.addEventListener('connectionstatechange', function() {
		if (self.pc.connectionState !== 'failed') {
			return
		}

		if (self.pc.iceConnectionState === 'failed') {
			return
		}

		// Work around Chromium bug where "iceConnectionState" never changes to
		// "failed" (it stays as "disconnected"). When that happens
		// "connectionState" actually does change to "failed", so the normal
		// handling of "iceConnectionState === failed" is triggered here.

		if (self.pc.localDescription.type === 'offer') {
			self.parent.emit('iceFailed', self)
			self.send('connectivityError')
		}
	})
	this.pc.addEventListener('signalingstatechange', this.emit.bind(this, 'signalingStateChange'))
	this.logger = this.parent.logger

	if (!options.receiverOnly) {
		// handle screensharing/broadcast mode
		if (options.type === 'screen') {
			if (this.parent.localScreen && this.sharemyscreen) {
				this.logger.log('adding local screen stream to peer connection')
				this.pc.addStream(this.parent.localScreen)
				this.broadcaster = options.broadcaster
			}
		} else {
			this.parent.sentStreams.forEach(function(stream) {
				stream.getTracks().forEach(function(track) {
					if (track.kind !== 'video' || self.sendVideoIfAvailable) {
						self.pc.addTrack(track, stream)
					}
				})
			})

			this.handleSentTrackReplacedBound = this.handleSentTrackReplaced.bind(this)
			// TODO What would happen if the track is replaced while the peer is
			// still negotiating the offer and answer?
			this.parent.on('sentTrackReplaced', this.handleSentTrackReplacedBound)

			this.handleSentTrackEnabledChangedBound = this.handleSentTrackEnabledChanged.bind(this)
			this.parent.on('sentTrackEnabledChanged', this.handleSentTrackEnabledChangedBound)
		}
	}

	// proxy events to parent
	this.on('*', function() {
		self.parent.emit.apply(self.parent, arguments)
	})
}

util.inherits(Peer, WildEmitter)

// Helper method to munge an SDP to enable simulcasting (Chrome only)
// Taken from janus.js (MIT license).
/* eslint-disable */
function mungeSdpForSimulcasting(sdp) {
	// Let's munge the SDP to add the attributes for enabling simulcasting
	// (based on https://gist.github.com/ggarber/a19b4c33510028b9c657)
	var lines = sdp.split("\r\n");
	var video = false;
	var ssrc = [ -1 ], ssrc_fid = [ -1 ];
	var cname = null, msid = null, mslabel = null, label = null;
	var insertAt = -1;
	for(var i=0; i<lines.length; i++) {
		var mline = lines[i].match(/m=(\w+) */);
		if(mline) {
			var medium = mline[1];
			if(medium === "video") {
				// New video m-line: make sure it's the first one
				if(ssrc[0] < 0) {
					video = true;
				} else {
					// We're done, let's add the new attributes here
					insertAt = i;
					break;
				}
			} else {
				// New non-video m-line: do we have what we were looking for?
				if(ssrc[0] > -1) {
					// We're done, let's add the new attributes here
					insertAt = i;
					break;
				}
			}
			continue;
		}
		if(!video)
			continue;
		var fid = lines[i].match(/a=ssrc-group:FID (\d+) (\d+)/);
		if(fid) {
			ssrc[0] = fid[1];
			ssrc_fid[0] = fid[2];
			lines.splice(i, 1); i--;
			continue;
		}
		if(ssrc[0]) {
			var match = lines[i].match('a=ssrc:' + ssrc[0] + ' cname:(.+)')
			if(match) {
				cname = match[1];
			}
			match = lines[i].match('a=ssrc:' + ssrc[0] + ' msid:(.+)')
			if(match) {
				msid = match[1];
			}
			match = lines[i].match('a=ssrc:' + ssrc[0] + ' mslabel:(.+)')
			if(match) {
				mslabel = match[1];
			}
			match = lines[i].match('a=ssrc:' + ssrc[0] + ' label:(.+)')
			if(match) {
				label = match[1];
			}
			if(lines[i].indexOf('a=ssrc:' + ssrc_fid[0]) === 0) {
				lines.splice(i, 1); i--;
				continue;
			}
			if(lines[i].indexOf('a=ssrc:' + ssrc[0]) === 0) {
				lines.splice(i, 1); i--;
				continue;
			}
		}
		if(lines[i].length == 0) {
			lines.splice(i, 1); i--;
			continue;
		}
	}
	if(ssrc[0] < 0) {
		// Couldn't find a FID attribute, let's just take the first video SSRC we find
		insertAt = -1;
		video = false;
		for(var i=0; i<lines.length; i++) {
			var mline = lines[i].match(/m=(\w+) */);
			if(mline) {
				var medium = mline[1];
				if(medium === "video") {
					// New video m-line: make sure it's the first one
					if(ssrc[0] < 0) {
						video = true;
					} else {
						// We're done, let's add the new attributes here
						insertAt = i;
						break;
					}
				} else {
					// New non-video m-line: do we have what we were looking for?
					if(ssrc[0] > -1) {
						// We're done, let's add the new attributes here
						insertAt = i;
						break;
					}
				}
				continue;
			}
			if(!video)
				continue;
			if(ssrc[0] < 0) {
				var value = lines[i].match(/a=ssrc:(\d+)/);
				if(value) {
					ssrc[0] = value[1];
					lines.splice(i, 1); i--;
					continue;
				}
			} else {
				var match = lines[i].match('a=ssrc:' + ssrc[0] + ' cname:(.+)')
				if(match) {
					cname = match[1];
				}
				match = lines[i].match('a=ssrc:' + ssrc[0] + ' msid:(.+)')
				if(match) {
					msid = match[1];
				}
				match = lines[i].match('a=ssrc:' + ssrc[0] + ' mslabel:(.+)')
				if(match) {
					mslabel = match[1];
				}
				match = lines[i].match('a=ssrc:' + ssrc[0] + ' label:(.+)')
				if(match) {
					label = match[1];
				}
				if(lines[i].indexOf('a=ssrc:' + ssrc_fid[0]) === 0) {
					lines.splice(i, 1); i--;
					continue;
				}
				if(lines[i].indexOf('a=ssrc:' + ssrc[0]) === 0) {
					lines.splice(i, 1); i--;
					continue;
				}
			}
			if(lines[i].length === 0) {
				lines.splice(i, 1); i--;
				continue;
			}
		}
	}
	if(ssrc[0] < 0) {
		// Still nothing, let's just return the SDP we were asked to munge
		console.warn("Couldn't find the video SSRC, simulcasting NOT enabled");
		return sdp;
	}
	if(insertAt < 0) {
		// Append at the end
		insertAt = lines.length;
	}
	// Generate a couple of SSRCs (for retransmissions too)
	// Note: should we check if there are conflicts, here?
	ssrc[1] = Math.floor(Math.random()*0xFFFFFFFF);
	ssrc[2] = Math.floor(Math.random()*0xFFFFFFFF);
	ssrc_fid[1] = Math.floor(Math.random()*0xFFFFFFFF);
	ssrc_fid[2] = Math.floor(Math.random()*0xFFFFFFFF);
	// Add attributes to the SDP
	for(var i=0; i<ssrc.length; i++) {
		if(cname) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' cname:' + cname);
			insertAt++;
		}
		if(msid) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' msid:' + msid);
			insertAt++;
		}
		if(mslabel) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' mslabel:' + mslabel);
			insertAt++;
		}
		if(label) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc[i] + ' label:' + label);
			insertAt++;
		}
		// Add the same info for the retransmission SSRC
		if(cname) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' cname:' + cname);
			insertAt++;
		}
		if(msid) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' msid:' + msid);
			insertAt++;
		}
		if(mslabel) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' mslabel:' + mslabel);
			insertAt++;
		}
		if(label) {
			lines.splice(insertAt, 0, 'a=ssrc:' + ssrc_fid[i] + ' label:' + label);
			insertAt++;
		}
	}
	lines.splice(insertAt, 0, 'a=ssrc-group:FID ' + ssrc[2] + ' ' + ssrc_fid[2]);
	lines.splice(insertAt, 0, 'a=ssrc-group:FID ' + ssrc[1] + ' ' + ssrc_fid[1]);
	lines.splice(insertAt, 0, 'a=ssrc-group:FID ' + ssrc[0] + ' ' + ssrc_fid[0]);
	lines.splice(insertAt, 0, 'a=ssrc-group:SIM ' + ssrc[0] + ' ' + ssrc[1] + ' ' + ssrc[2]);
	sdp = lines.join("\r\n");
	if(!sdp.endsWith("\r\n"))
		sdp += "\r\n";
	return sdp;
}
/* eslint-enable */

Peer.prototype.offer = function(options) {
	const sendVideo = this.sendVideoIfAvailable && this.type !== 'screen'
	if (sendVideo && this.enableSimulcast && adapter.browserDetails.browser === 'firefox') {
		console.debug('Enabling Simulcasting for Firefox (RID)')
		const sender = this.pc.getSenders().find(function(s) {
			return s.track.kind === 'video'
		})
		if (sender) {
			let parameters = sender.getParameters()
			if (!parameters) {
				parameters = {}
			}
			parameters.encodings = [
				{
					rid: 'h',
					active: true,
					maxBitrate: this.maxBitrates.high,
				},
				{
					rid: 'm',
					active: true,
					maxBitrate: this.maxBitrates.medium,
					scaleResolutionDownBy: 2,
				},
				{
					rid: 'l',
					active: true,
					maxBitrate: this.maxBitrates.low,
					scaleResolutionDownBy: 4,
				},
			]
			sender.setParameters(parameters)
		}
	}
	this.pc.createOffer(options).then(function(offer) {
		if (sendVideo && this.enableSimulcast) {
			// This SDP munging only works with Chrome (Safari STP may support it too)
			if (adapter.browserDetails.browser === 'chrome' || adapter.browserDetails.browser === 'safari') {
				console.debug('Enabling Simulcasting for Chrome (SDP munging)')
				offer.sdp = mungeSdpForSimulcasting(offer.sdp)
			} else if (adapter.browserDetails.browser !== 'firefox') {
				console.debug('Simulcast can only be enabled on Chrome or Firefox')
			}
		}

		this.pc.setLocalDescription(offer).then(function() {
			if (this.parent.config.nick) {
				// The offer is a RTCSessionDescription that only serializes
				// its own attributes to JSON, so if extra attributes are needed
				// a regular object has to be sent instead.
				offer = {
					type: offer.type,
					sdp: offer.sdp,
					nick: this.parent.config.nick,
				}
			}
			this.send('offer', offer)
		}.bind(this)).catch(function(error) {
			console.warn('setLocalDescription for offer failed: ', error)
		})
	}.bind(this)).catch(function(error) {
		console.warn('createOffer failed: ', error)
	})
}

Peer.prototype.handleOffer = function(offer) {
	this.pc.setRemoteDescription(offer).then(function() {
		this._blockRemoteVideoIfNeeded()

		this.answer()
	}.bind(this)).catch(function(error) {
		console.warn('setRemoteDescription for offer failed: ', error)
	})
}

Peer.prototype._getTransceiverKind = function(transceiver) {
	// Transceivers for HPB subscribers have the transceiver kind in its mid.
	if (transceiver.mid === 'audio' || transceiver.mid === 'video') {
		return transceiver.mid
	}

	// In general, the transceiver kind can be got from the receiver track, as
	// it will always be there, even if the transceiver is inactive or the
	// remote sender never had a track.
	if (transceiver.receiver && transceiver.receiver.track) {
		return transceiver.receiver.track.kind
	}

	console.debug('Transceiver kind could not be determined: ', transceiver)

	return null
}

/**
 * Blocks remote video based on "_remoteVideoShouldBeBlocked".
 *
 * 'remoteVideoBlocked' is emitted if the blocked state changes.
 *
 * Currently remote video can be blocked only when the HPB is used, so this
 * method should be called immediately before creating the answer (the answer
 * must be created in the same "tick" that this method is called).
 *
 * Note that if the transceiver direction changes after creating the answer but
 * before setting it as the local description the "negotiationneeded" event will
 * be automatically emitted again.
 */
Peer.prototype._blockRemoteVideoIfNeeded = function() {
	const remoteVideoWasBlocked = this._remoteVideoBlocked

	this._remoteVideoBlocked = undefined

	this.pc.getTransceivers().forEach(transceiver => {
		if (transceiver.mid === 'video' && !transceiver.stopped) {
			if (this._remoteVideoShouldBeBlocked) {
				transceiver.direction = 'inactive'

				this._remoteVideoBlocked = true
			} else {
				this._remoteVideoBlocked = false
			}
		}
	})

	if (remoteVideoWasBlocked !== this._remoteVideoBlocked) {
		this.emit('remoteVideoBlocked', this._remoteVideoBlocked)
	}
}

Peer.prototype.answer = function() {
	this.pc.createAnswer().then(function(answer) {
		this.pc.setLocalDescription(answer).then(function() {
			if (this.parent.config.nick) {
				// The answer is a RTCSessionDescription that only serializes
				// its own attributes to JSON, so if extra attributes are needed
				// a regular object has to be sent instead.
				answer = {
					type: answer.type,
					sdp: answer.sdp,
					nick: this.parent.config.nick,
				}
			}
			this.send('answer', answer)
		}.bind(this)).catch(function(error) {
			console.warn('setLocalDescription for answer failed: ', error)
		})
	}.bind(this)).catch(function(error) {
		console.warn('createAnswer failed: ', error)
	})
}

Peer.prototype.handleAnswer = function(answer) {
	this.pc.setRemoteDescription(answer).catch(function(error) {
		console.warn('setRemoteDescription for answer failed: ', error)
	})
}

Peer.prototype.selectSimulcastStream = function(substream, temporal) {
	if (this.substream === substream && this.temporal === temporal) {
		return
	}

	console.debug('Changing simulcast stream', this.id, this, substream, temporal)
	this.send('selectStream', {
		substream,
		temporal,
	})
	this.substream = substream
	this.temporal = temporal
}

Peer.prototype.handleMessage = function(message) {
	const self = this

	this.logger.log('getting', message.type, message)

	if (message.type === 'offer') {
		if (!this.nick) {
			this.nick = message.payload.nick
		}
		delete message.payload.nick
		this.handleOffer(message.payload)
	} else if (message.type === 'answer') {
		if (!this.nick) {
			this.nick = message.payload.nick
		}
		delete message.payload.nick
		this.handleAnswer(message.payload)
	} else if (message.type === 'candidate') {
		this.pc.addIceCandidate(message.payload.candidate)
	} else if (message.type === 'connectivityError') {
		this.parent.emit('connectivityError', self)
	} else if (message.type === 'mute') {
		this.parent.emit('mute', { id: message.from, name: message.payload.name })
	} else if (message.type === 'unmute') {
		this.parent.emit('unmute', { id: message.from, name: message.payload.name })
	} else if (message.type === 'endOfCandidates') {
		this.pc.addIceCandidate('')
	} else if (message.type === 'unshareScreen') {
		this.parent.emit('unshareScreen', { id: message.from })
		this.end()
	}
}

// send via signalling channel
Peer.prototype.send = function(messageType, payload) {
	const message = {
		to: this.id,
		sid: this.sid,
		broadcaster: this.broadcaster,
		roomType: this.type,
		type: messageType,
		payload,
	}
	this.logger.log('sending', messageType, message)
	this.parent.emit('message', message)
}

// send via data channel
// returns true when message was sent and false if channel is not open
Peer.prototype.sendDirectly = function(channel, messageType, payload) {
	const message = {
		type: messageType,
		payload,
	}
	this.logger.log('sending via datachannel', channel, messageType, message)
	const dc = this.getDataChannel(channel)
	if (!dc) {
		return false
	}
	if (dc.readyState !== 'open') {
		if (!Object.prototype.hasOwnProperty.call(this.pendingDCMessages, channel)) {
			this.pendingDCMessages[channel] = []
		}
		this.pendingDCMessages[channel].push(message)
		return false
	}
	dc.send(JSON.stringify(message))
	return true
}

// Internal method registering handlers for a data channel and emitting events on the peer
Peer.prototype._observeDataChannel = function(channel) {
	const self = this
	channel.onclose = this.emit.bind(this, 'channelClose', channel)
	channel.onerror = this.emit.bind(this, 'channelError', channel)
	channel.onmessage = function(event) {
		self.emit('channelMessage', self, channel.label, JSON.parse(event.data), channel, event)
	}
	channel.onopen = function() {
		self.emit('channelOpen', channel)
		// Check if there are messages that could not be send
		if (Object.prototype.hasOwnProperty.call(self.pendingDCMessages, channel.label)) {
			const pendingMessages = self.pendingDCMessages[channel.label].slice()
			self.pendingDCMessages[channel.label] = []
			for (let i = 0; i < pendingMessages.length; i++) {
				self.sendDirectly(channel.label, pendingMessages[i].type, pendingMessages[i].payload)
			}
		}
	}
}

// Fetch or create a data channel by the given name
Peer.prototype.getDataChannel = function(name, opts) {
	if (!webrtcSupport.supportDataChannel) {
		return this.emit('error', new Error('createDataChannel not supported'))
	}
	if (!this.enableDataChannels) {
		return null
	}
	let channel = this.channels[name]
	opts || (opts = {})
	if (channel) {
		return channel
	}
	// if we don't have one by this label, create it
	channel = this.channels[name] = this.pc.createDataChannel(name, opts)
	this._observeDataChannel(channel)
	return channel
}

Peer.prototype.onIceCandidate = function(event) {
	const candidate = event.candidate
	if (this.closed) {
		return
	}
	if (candidate) {
		// Retain legacy data structure for compatibility with
		// mobile clients.
		const expandedCandidate = {
			candidate: {
				candidate: candidate.candidate,
				sdpMid: candidate.sdpMid,
				sdpMLineIndex: candidate.sdpMLineIndex,
			},
		}
		this.send('candidate', expandedCandidate)
	} else {
		this.logger.log('End of candidates.')
	}
}

Peer.prototype.start = function() {
	// well, the webrtc api requires that we either
	// a) create a datachannel a priori
	// b) do a renegotiation later to add the SCTP m-line
	// Let's do (a) first...
	this.getDataChannel('simplewebrtc')

	this.offer(this.receiveMedia)
}

Peer.prototype.icerestart = function() {
	const constraints = this.receiveMedia
	constraints.iceRestart = true
	this.offer(constraints)
}

Peer.prototype.end = function() {
	if (this.closed) {
		return
	}
	this.pc.close()
	this.handleStreamRemoved()
	this.parent.off('sentTrackReplaced', this.handleSentTrackReplacedBound)
	this.parent.off('sentTrackEnabledChanged', this.handleSentTrackEnabledChangedBound)

	this.parent.emit('peerEnded', this)
}

Peer.prototype.handleSentTrackReplaced = function(newTrack, oldTrack, stream) {
	this._pendingReplaceTracksQueue.push({ newTrack, oldTrack, stream })

	this._processPendingReplaceTracks()
}

/**
 * Process pending replace track actions.
 *
 * All the pending replace track actions are executed from the oldest to the
 * newest, waiting until the previous action was executed before executing the
 * next one.
 *
 * The process may be stopped if the connection is lost, or if a track needs to
 * be added rather than replaced, which requires a renegotiation. In both cases
 * the process will start again once the connection is restablished.
 *
 * @return {Promise} a Promise fulfilled when the processing ends; if it was
 *          completed the resolved value is true, and if it was stopped before
 *          finishing the resolved value is false.
 */
Peer.prototype._processPendingReplaceTracks = function() {
	if (this._processPendingReplaceTracksPromise) {
		return this._processPendingReplaceTracksPromise
	}

	this._processPendingReplaceTracksPromise = this._processPendingReplaceTracksAsync()

	// For compatibility with older browsers "finally" should not be used on
	// Promises.
	this._processPendingReplaceTracksPromise.then(() => {
		this._processPendingReplaceTracksPromise = null
	}).catch(() => {
		this._processPendingReplaceTracksPromise = null
	})

	return this._processPendingReplaceTracksPromise
}

Peer.prototype._processPendingReplaceTracksAsync = async function() {
	while (this._pendingReplaceTracksQueue.length > 0) {
		if (this.pc.iceConnectionState === 'new') {
			// Do not replace the tracks when the connection has not started
			// yet, as Firefox can get "stuck" and not replace the tracks even
			// if tried later again once connected.
			return false
		}

		const pending = this._pendingReplaceTracksQueue.shift()

		try {
			await this._replaceTrack(pending.newTrack, pending.oldTrack, pending.stream)
		} catch (exception) {
			// If the track is added instead of replaced a renegotiation will be
			// needed, so stop replacing tracks.
			return false
		}
	}

	return true
}

/**
 * Replaces the old track with the new track in the appropriate sender.
 *
 * If the new track is disabled the old track will be replaced by a null track
 * instead, which stops the sent data. The old and new tracks can be the same
 * track, which can be used to start or stop sending the track data depending on
 * whether the track is enabled or disabled (at the time of being passed to this
 * method).
 *
 * If a new track is provided but no sender was found the new track is added
 * instead of replaced (which will require a renegotiation).
 *
 * The method returns a promise which is fulfilled once the track was replaced
 * in the appropriate sender, or immediately if no sender was found and no track
 * was added. If a track had to be added the promise is rejected instead.
 *
 * @param {MediaStreamTrack|null} newTrack the new track to set.
 * @param {MediaStreamTrack|null} oldTrack the old track to be replaced.
 * @param {MediaStream} stream the stream that the new track belongs to.
 * @return {Promise}
 */
Peer.prototype._replaceTrack = async function(newTrack, oldTrack, stream) {
	let senderFound = false

	// The track should be replaced in just one sender, but an array of promises
	// is used to be on the safe side.
	const replaceTrackPromises = []

	this.pc.getSenders().forEach(sender => {
		if (sender.track !== oldTrack && sender.trackDisabled !== oldTrack) {
			return
		}

		if ((sender.track || sender.trackDisabled) && !oldTrack) {
			return
		}

		if (!sender.track && !newTrack) {
			// The old track was disabled and thus already stopped, so it does
			// not need to be replaced, but the null track needs to be set as
			// the disabled track.
			if (sender.trackDisabled === oldTrack) {
				sender.trackDisabled = newTrack
			}

			return
		}

		if (!sender.kind && sender.track) {
			sender.kind = sender.track.kind
		} else if (!sender.kind && sender.trackDisabled) {
			sender.kind = sender.trackDisabled.kind
		} else if (!sender.kind) {
			this.pc.getTransceivers().forEach(transceiver => {
				if (transceiver.sender === sender) {
					sender.kind = this._getTransceiverKind(transceiver)
				}
			})
		}

		// A null track can match on audio and video senders, so it needs to be
		// ensured that the sender kind and the new track kind are compatible.
		// However, in some cases it may not be possible to know the sender
		// kind. In those cases just go ahead and try to replace the track; if
		// the kind does not match then replacing the track will fail, but this
		// should not prevent replacing the track with a proper one later, nor
		// affect any other sender.
		if (!sender.track && sender.kind && sender.kind !== newTrack.kind) {
			return
		}

		senderFound = true

		// Save reference to trackDisabled to be able to restore it if the track
		// can not be replaced.
		const oldTrackDisabled = sender.trackDisabled

		if (newTrack && !newTrack.enabled) {
			sender.trackDisabled = newTrack
		} else {
			sender.trackDisabled = null
		}

		if (!sender.track && !newTrack.enabled) {
			// Nothing to replace now, it will be done once the track is
			// enabled.
			return
		}

		if (sender.track && newTrack && !newTrack.enabled) {
			// Replace with a null track to stop the sender.
			newTrack = null
		}

		const replaceTrackPromise = sender.replaceTrack(newTrack)

		replaceTrackPromise.catch(error => {
			sender.trackDisabled = oldTrackDisabled

			if (error.name === 'InvalidModificationError') {
				console.debug('Track could not be replaced, negotiation needed')
			} else {
				console.error('Track could not be replaced: ', error, oldTrack, newTrack)
			}
		})

		replaceTrackPromises.push(replaceTrackPromise)
	})

	// If the call started when the audio or video device was not active there
	// will be no sender for that type. In that case the track needs to be added
	// instead of replaced.
	if (!senderFound && newTrack) {
		this.pc.addTrack(newTrack, stream)

		return Promise.reject(new Error('Track added instead of replaced'))
	}

	return Promise.allSettled(replaceTrackPromises)
}

Peer.prototype.handleSentTrackEnabledChanged = function(track, stream) {
	const sender = this.pc.getSenders().find(sender => sender.track === track)
	const stoppedSender = this.pc.getSenders().find(sender => sender.trackDisabled === track)

	if (track.enabled && stoppedSender) {
		this.handleSentTrackReplacedBound(track, track, stream)
	} else if (!track.enabled && sender) {
		this.handleSentTrackReplacedBound(track, track, stream)
	}
}

Peer.prototype.setRemoteVideoBlocked = function(remoteVideoBlocked) {
	// If the HPB is not used or if it is used and this is a sender peer the
	// remote video can not be blocked.
	// Besides that the remote video is not blocked either if the signaling
	// server does not support updating the subscribers; in that case a new
	// connection would need to be established and due to this the audio would
	// be interrupted during the connection change.
	if (!this.receiverOnly || !this.parent.config.connection.hasFeature('update-sdp')) {
		return
	}

	// If the HPB is used the remote video can be blocked through a standard
	// WebRTC renegotiation or by toggling the video directly in Janus. The last
	// one is preferred, as it requires less signaling messages to be exchanged
	// and, besides that, the browser starts to decode the video faster once
	// enabled again.
	if (this.receiverOnly && this.parent.config.connection.hasFeature('update-sdp')) {
		this.send('selectStream', {
			video: !remoteVideoBlocked,
		})

		return
	}

	this._remoteVideoShouldBeBlocked = remoteVideoBlocked

	// The "negotiationneeded" event is emitted if needed based on the direction
	// changes.
	// Note that there will be a video transceiver even if the remote
	// participant is sending a null video track (either because there is a
	// camera but the video is disabled or because the camera was removed during
	// the call), so a renegotiation could be needed also in that case.
	this.pc.getTransceivers().forEach(transceiver => {
		if (transceiver.mid === 'video' && !transceiver.stopped) {
			if (remoteVideoBlocked) {
				transceiver.direction = 'inactive'
			} else {
				transceiver.direction = 'recvonly'
			}
		}
	})
}

Peer.prototype.handleRemoteStreamAdded = function(event) {
	const self = this
	if (this.stream) {
		this.logger.warn('Already have a remote stream')
	} else {
		this.stream = event.stream

		this.stream.getTracks().forEach(function(track) {
			track.addEventListener('ended', function() {
				if (isAllTracksEnded(self.stream)) {
					self.end()
				}
			})
		})

		this.parent.emit('peerStreamAdded', this)
	}
}

Peer.prototype.handleStreamRemoved = function() {
	const peerIndex = this.parent.peers.indexOf(this)
	if (peerIndex > -1) {
		this.parent.peers.splice(peerIndex, 1)
		this.closed = true
		this.parent.emit('peerStreamRemoved', this)
	}
}

Peer.prototype.handleDataChannelAdded = function(event) {
	const channel = event.channel
	this.channels[channel.label] = channel
	this._observeDataChannel(channel)
}

export default Peer
