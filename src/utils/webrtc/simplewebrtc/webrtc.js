/* global module */

import util from 'util'

import mockconsole from 'mockconsole'
import webrtcSupport from 'webrtcsupport'

import localMedia from './localmedia.js'
import Peer from './peer.js'

/**
 * @param {object} opts the options object.
 */
function WebRTC(opts) {
	const self = this
	const options = opts || {}
	this.config = {
		debug: false,
		// makes the entire PC config overridable
		peerConnectionConfig: {
			iceServers: [],
		},
		receiveMedia: {
			offerToReceiveAudio: 1,
			offerToReceiveVideo: 1,
		},
		enableDataChannels: true,
		enableSimulcast: false,
		maxBitrates: {
			high: 900000,
			medium: 300000,
			low: 100000,
		},
	}
	let item

	// We also allow a 'logger' option. It can be any object that implements
	// log, warn, and error methods.
	// We log nothing by default, following "the rule of silence":
	// http://www.linfo.org/rule_of_silence.html
	this.logger = (function() {
		// we assume that if you're in debug mode and you didn't
		// pass in a logger, you actually want to log as much as
		// possible.
		if (opts.debug) {
			return opts.logger || console
		} else {
		// or we'll use your logger which should have its own logic
		// for output. Or we'll return the no-op.
			return opts.logger || mockconsole
		}
	}())

	// set options
	for (item in options) {
		if (Object.prototype.hasOwnProperty.call(options, item)) {
			this.config[item] = options[item]
		}
	}

	// check for support
	if (!webrtcSupport.support) {
		this.logger.error('Your browser doesn\'t seem to support WebRTC')
	}

	// where we'll store our peer connections
	this.peers = []

	// call localMedia constructor
	localMedia.call(this, this.config)

	this.on('unshareScreen', function(message) {
		// End peers we were receiving the screensharing stream from.
		const peers = self.getPeers(message.id, 'screen')
		peers.forEach(function(peer) {
			if (!peer.sharemyscreen) {
				peer.end()
			}
		})
	})

	// log events in debug mode
	if (this.config.debug) {
		this.on('*', function(event, val1, val2) {
			let logger
			// if you didn't pass in a logger and you explicitly turning on debug
			// we're just going to assume you're wanting log output with console
			if (self.config.logger === mockconsole) {
				logger = console
			} else {
				logger = self.logger
			}
			logger.log('event:', event, val1, val2)
		})
	}
}

util.inherits(WebRTC, localMedia)

WebRTC.prototype.createPeer = function(opts) {
	opts.parent = this
	const peer = new Peer(opts)
	this.peers.push(peer)
	return peer
}

// removes peers
WebRTC.prototype.removePeers = function(id, type) {
	this.getPeers(id, type).forEach(function(peer) {
		peer.end()
	})
}

// fetches all Peer objects by session id and/or type
WebRTC.prototype.getPeers = function(sessionId, type) {
	return this.peers.filter(function(peer) {
		return (!sessionId || peer.id === sessionId) && (!type || peer.type === type)
	})
}

// sends message to all
WebRTC.prototype.sendToAll = function(message, payload) {
	this.emit('sendToAll', message, payload)
}

// sends message to all using a datachannel
// only sends to anyone who has an open datachannel
WebRTC.prototype.sendDirectlyToAll = function(channel, message, payload) {
	this.peers.forEach(function(peer) {
		if (peer.enableDataChannels) {
			peer.sendDirectly(channel, message, payload)
		}
	})
}

export default WebRTC
