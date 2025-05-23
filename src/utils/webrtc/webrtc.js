/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	showError,
	TOAST_PERMANENT_TIMEOUT,
	TOAST_DEFAULT_TIMEOUT,
} from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import SimpleWebRTC from './simplewebrtc/simplewebrtc.js'
import { PARTICIPANT } from '../../constants.ts'
import store from '../../store/index.js'
import { Sounds } from '../sounds.js'

let webrtc
const spreedPeerConnectionTable = []

let previousUsersInRoom = []
let usersInCallMapping = {}
let ownPeer = null
let ownScreenPeer = null
let selfInCall = PARTICIPANT.CALL_FLAG.DISCONNECTED
// Special variable to know when the local user explicitly joined and left the
// call; this is needed to know when the user was kicked out from the call by a
// moderator and discard signaling events if received when not in the call.
let localUserInCall = false
const delayedConnectionToPeer = []
let callParticipantCollection = null
let localCallParticipantModel = null
let showedTURNWarning = false
let sendCurrentStateWithRepetitionTimeout = null

/**
 * @param {Array} a Source object
 * @param {Array} b Object to find all items in
 */
function arrayDiff(a, b) {
	return a.filter((i) => !b.includes(i))
}

/**
 * @param {object} signaling The signaling object
 * @param {string} sessionId The user's sessionId
 */
function createScreensharingPeer(signaling, sessionId) {
	const currentSessionId = signaling.getSessionId()
	const useMcu = signaling.hasFeature('mcu')

	if (useMcu && !webrtc.webrtc.getPeers(currentSessionId, 'screen').length) {
		if (ownScreenPeer) {
			ownScreenPeer.end()
		}

		// Create own publishing stream.
		ownScreenPeer = webrtc.webrtc.createPeer({
			id: currentSessionId,
			type: 'screen',
			sharemyscreen: true,
			enableDataChannels: false,
			enableSimulcast: signaling.hasFeature('simulcast'),
			receiveMedia: {
				offerToReceiveAudio: 0,
				offerToReceiveVideo: 0,
			},
			broadcaster: currentSessionId,
		})
		webrtc.emit('createdPeer', ownScreenPeer)
		ownScreenPeer.start()

		localCallParticipantModel.setScreenPeer(ownScreenPeer)
	}

	if (sessionId === currentSessionId) {
		return
	}

	if (useMcu) {
		// TODO(jojo): Already create peer object to avoid duplicate offers.
		// TODO(jojo): We should use "requestOffer" as with regular
		// audio/video peers. Not possible right now as there is no way
		// for clients to know that screensharing is active and an offer
		// from the MCU should be requested.
		signaling.sendOffer(sessionId, 'screen')
	} else if (!useMcu) {
		const screenPeers = webrtc.webrtc.getPeers(sessionId, 'screen')
		const screenPeerSharedTo = screenPeers.find(function(screenPeer) {
			return screenPeer.sharemyscreen === true
		})
		if (!screenPeerSharedTo) {
			const peer = webrtc.webrtc.createPeer({
				id: sessionId,
				type: 'screen',
				sharemyscreen: true,
				enableDataChannels: false,
				enableSimulcast: signaling.hasFeature('simulcast'),
				receiveMedia: {
					offerToReceiveAudio: 0,
					offerToReceiveVideo: 0,
				},
				broadcaster: currentSessionId,
			})
			webrtc.emit('createdPeer', peer)
			peer.start()
		}
	}
}

/**
 * @param {object} signaling The signaling object
 */
function checkStartPublishOwnPeer(signaling) {
	'use strict'
	const currentSessionId = signaling.getSessionId()
	if (!webrtc.webrtc.localStreams.length || webrtc.webrtc.getPeers(currentSessionId, 'video').length) {
		// No media yet or already publishing.
		return
	}

	if (ownPeer) {
		if (delayedConnectionToPeer[ownPeer.id]) {
			clearInterval(delayedConnectionToPeer[ownPeer.id])
			delete delayedConnectionToPeer[ownPeer.id]
		}
		ownPeer.end()
	}

	const createPeer = function() {
		// Create own publishing stream.
		ownPeer = webrtc.webrtc.createPeer({
			id: currentSessionId,
			type: 'video',
			enableDataChannels: true,
			enableSimulcast: signaling.hasFeature('simulcast'),
			receiveMedia: {
				offerToReceiveAudio: 0,
				offerToReceiveVideo: 0,
			},
			sendVideoIfAvailable: signaling.getSendVideoIfAvailable(),
		})
		webrtc.emit('createdPeer', ownPeer)
		ownPeer.start()

		localCallParticipantModel.setPeer(ownPeer)
	}

	createPeer()

	delayedConnectionToPeer[ownPeer.id] = setInterval(function() {
		// New offers are periodically sent until a connection is established.
		// As an offer can not be sent again from an existing peer it must be
		// removed and a new one must be created from scratch.
		if (ownPeer) {
			ownPeer.end()
		}

		if (currentSessionId !== signaling.getSessionId()) {
			console.debug('No answer received for own peer but current session id changed, not sending offer again', currentSessionId, signaling.getSessionId())

			clearInterval(delayedConnectionToPeer[currentSessionId])
			delete delayedConnectionToPeer[currentSessionId]

			return
		}

		console.debug('No answer received for own peer, sending offer again', currentSessionId)
		createPeer()
	}, 10000)
}

/**
 *
 */
function sendCurrentMediaState() {
	if (!webrtc.webrtc.isVideoEnabled()) {
		webrtc.webrtc.emit('videoOff')
	} else {
		webrtc.webrtc.emit('videoOn')
	}
	if (!webrtc.webrtc.isAudioEnabled()) {
		webrtc.webrtc.emit('audioOff')
	} else {
		webrtc.webrtc.emit('audioOn')

		if (!webrtc.webrtc.isSpeaking()) {
			webrtc.webrtc.emit('stoppedSpeaking')
		} else {
			webrtc.webrtc.emit('speaking')
		}
	}
}

// TODO The participant name should be got from the participant list, but it is
// not currently possible to associate a Nextcloud ID with a standalone
// signaling ID for guests.
/**
 *
 */
function sendCurrentNick() {
	webrtc.webrtc.emit('nickChanged', store.getters.getDisplayName())
}

/**
 * @param {number} timeout Time until we give up retrying
 */
function sendCurrentStateWithRepetition(timeout) {
	if (!timeout) {
		timeout = 0

		clearTimeout(sendCurrentStateWithRepetitionTimeout)
	}

	sendCurrentStateWithRepetitionTimeout = setTimeout(function() {
		sendCurrentMediaState()
		sendCurrentNick()

		if (!timeout) {
			timeout = 1000
		} else {
			timeout *= 2
		}

		if (timeout > 16000) {
			sendCurrentStateWithRepetitionTimeout = null
			return
		}

		sendCurrentStateWithRepetition(timeout)
	}, timeout)
}

/**
 * @param {object} user The user to check
 * @return {boolean} True if the user has an audio or video stream
 */
function userHasStreams(user) {
	let flags = user
	if (Object.prototype.hasOwnProperty.call(flags, 'inCall')) {
		flags = flags.inCall
	}
	flags = flags || PARTICIPANT.CALL_FLAG.DISCONNECTED
	const REQUIRED_FLAGS = PARTICIPANT.CALL_FLAG.WITH_AUDIO | PARTICIPANT.CALL_FLAG.WITH_VIDEO
	return (flags & REQUIRED_FLAGS) !== 0
}

/**
 * @param {object} signaling The signaling object
 * @param {Array} newUsers Newly added participants
 * @param {Array} disconnectedSessionIds Remove participants
 */
function usersChanged(signaling, newUsers, disconnectedSessionIds) {
	'use strict'
	const currentSessionId = signaling.getSessionId()

	const useMcu = signaling.hasFeature('mcu')

	let playJoinSound = false
	let playLeaveSound = false

	newUsers.forEach(function(user) {
		if (!user.inCall) {
			return
		}

		// TODO(fancycode): Adjust property name of internal PHP backend to be all lowercase.
		const sessionId = user.sessionId || user.sessionid
		if (!sessionId || sessionId === currentSessionId || previousUsersInRoom.includes(sessionId)) {
			if (sessionId === currentSessionId && previousUsersInRoom.includes(sessionId)) {
				Sounds.playJoin(true, newUsers.length === 1)
			}
			return
		}

		previousUsersInRoom.push(sessionId)

		// Use null to differentiate between guest (null) and not known yet
		// (undefined).
		// TODO(fancycode): Adjust property name of internal PHP backend to be all lowercase.
		const userId = user.userId || user.userid || null

		// When the external signaling server is used the Nextcloud session id
		// will be provided in its own property. When the internal signaling
		// server is used the Nextcloud session id and the signaling session id
		// are the same and thus set from the signaling session id.
		const nextcloudSessionId = user.nextcloudSessionId || user.nextcloudsessionid || sessionId

		let callParticipantModel = callParticipantCollection.get(sessionId)
		if (!callParticipantModel) {
			callParticipantModel = callParticipantCollection.add({
				peerId: sessionId,
				webRtc: webrtc,
			})
		}
		callParticipantModel.setActor(user.actorType, user.actorId)
		callParticipantModel.setUserId(userId)
		callParticipantModel.setNextcloudSessionId(nextcloudSessionId)
		if (user.internal) {
			callParticipantModel.set('internal', true)
		}

		// When the MCU is used and the other participant has no streams or
		// when no MCU is used and neither the local participant nor the
		// other one has no streams there will be no Peer for that other
		// participant, so a null Peer needs to be explicitly set now.
		if ((signaling.hasFeature('mcu') && user && !userHasStreams(user))
			|| (!signaling.hasFeature('mcu') && user && !userHasStreams(user) && !webrtc.webrtc.localStreams.length)) {
			callParticipantModel.setPeer(null)

			// As there is no Peer for the other participant the current state
			// will not be sent once it is connected, so it needs to be sent
			// now.
			// When there is no MCU this is only needed for the nick; as the
			// local participant has no streams it will be automatically marked
			// with audio and video not available on the other end, so there is
			// no need to send the media state.
			if (signaling.hasFeature('mcu')) {
				sendCurrentStateWithRepetition()
			} else {
				sendCurrentNick()
			}
		}

		playJoinSound = true

		const createPeer = function() {
			const peer = webrtc.webrtc.createPeer({
				id: sessionId,
				type: 'video',
				enableDataChannels: true,
				enableSimulcast: signaling.hasFeature('simulcast'),
				receiveMedia: {
					offerToReceiveAudio: 1,
					offerToReceiveVideo: 1,
				},
				sendVideoIfAvailable: signaling.getSendVideoIfAvailable(),
			})
			webrtc.emit('createdPeer', peer)
			peer.start()
		}

		if (!webrtc.webrtc.getPeers(sessionId, 'video').length) {
			if (useMcu && userHasStreams(user)) {
				// TODO(jojo): Already create peer object to avoid duplicate offers.
				signaling.requestOffer(user, 'video')

				// Clearing the previous delayedConnectionToPeer should not be
				// needed here, but just in case.
				clearInterval(delayedConnectionToPeer[user.sessionId])

				delayedConnectionToPeer[user.sessionId] = setInterval(function() {
					console.debug('No offer received for new peer, request offer again', sessionId)

					signaling.requestOffer(user, 'video')
				}, 10000)
			} else if (!useMcu && userHasStreams(selfInCall) && (!userHasStreams(user) || sessionId < currentSessionId)) {
				// To avoid overloading the user joining a room (who previously called
				// all the other participants), we decide who calls who by comparing
				// the session ids of the users: "larger" ids call "smaller" ones.
				console.debug('Starting call with', user, sessionId)
				createPeer()
			} else if (!useMcu && userHasStreams(selfInCall) && userHasStreams(user) && sessionId > currentSessionId) {
				// If the remote peer is not aware that it was disconnected
				// from the current peer the remote peer will not send a new
				// offer; thus, if the current peer does not receive a new
				// offer in a reasonable time, the current peer calls the
				// remote peer instead of waiting to be called to
				// reestablish the connection.
				delayedConnectionToPeer[sessionId] = setInterval(function() {
					// New offers are periodically sent until a connection
					// is established. As an offer can not be sent again
					// from an existing peer it must be removed and a new
					// one must be created from scratch.
					webrtc.webrtc.getPeers(sessionId, 'video').forEach(function(peer) {
						peer.end()
					})

					console.debug('No offer nor answer received, sending offer again', sessionId)
					createPeer()
				}, 10000)
			} else {
				console.debug('User has no streams, not sending another offer', sessionId)
			}
		}

		// Send shared screen to new participants
		if (webrtc.getLocalScreen()) {
			createScreensharingPeer(signaling, sessionId)
		}
	})

	disconnectedSessionIds.forEach(function(sessionId) {
		console.debug('Remove disconnected peer', sessionId)
		webrtc.removePeers(sessionId)

		if (callParticipantCollection.remove(sessionId)) {
			playLeaveSound = true
		}

		if (delayedConnectionToPeer[sessionId]) {
			clearInterval(delayedConnectionToPeer[sessionId])
			delete delayedConnectionToPeer[sessionId]
			playLeaveSound = true
		}
	})

	previousUsersInRoom = arrayDiff(previousUsersInRoom, disconnectedSessionIds)

	if (selfInCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED) {
		if (playJoinSound) {
			Sounds.playJoin()
		} else if (playLeaveSound) {
			Sounds.playLeave(false, previousUsersInRoom.length === 0)
		}
	}
}

/**
 * @param {object} signaling The signaling object
 * @param {object} users Participant list with sessionId as key
 */
function usersInCallChanged(signaling, users) {
	const previousSelfInCall = selfInCall

	// The passed list are the users that are currently in the room,
	// i.e. that are in the call and should call each other.
	const currentSessionId = signaling.getSessionId()
	const currentUsersInRoom = []
	const userMapping = {}
	selfInCall = PARTICIPANT.CALL_FLAG.DISCONNECTED
	let sessionId
	for (sessionId in users) {
		if (!Object.prototype.hasOwnProperty.call(users, sessionId)) {
			continue
		}
		const user = users[sessionId]
		if (!user.inCall) {
			continue
		}

		if (sessionId === currentSessionId) {
			selfInCall = user.inCall
			continue
		}

		currentUsersInRoom.push(sessionId)
		userMapping[sessionId] = user
	}

	if (signaling.hasFeature('mcu') && (ownPeer || (currentUsersInRoom.length > 0 && webrtc.webrtc.localStreams.length))) {
		checkStartPublishOwnPeer(signaling)

		localCallParticipantModel.setPeerNeeded(true)
	} else {
		localCallParticipantModel.setPeerNeeded(false)
	}

	if (previousSelfInCall === PARTICIPANT.CALL_FLAG.DISCONNECTED
		&& selfInCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED) {
		Sounds.playJoin(true, Object.keys(userMapping).length === 0)
	} else if (previousSelfInCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		&& selfInCall === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
		Sounds.playLeave(true)
	}

	// Besides the participant state it also needs to be checked whether the
	// local user left the call already or not (either explicitly or due to a
	// forced reconnection) to avoid trying to leave the call twice in the
	// store.
	if (previousSelfInCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		&& selfInCall === PARTICIPANT.CALL_FLAG.DISCONNECTED
		&& localUserInCall) {
		console.info('Force leaving the call for current participant')

		store.dispatch('leaveCall', {
			token: store.getters.getToken(),
			participantIdentifier: store.getters.getParticipantIdentifier(),
		})

		// Do not return to disconnect already from the other participants
		// without waiting for another signaling event about changed users.
	}

	if (selfInCall === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
		// Own session is no longer in the call, disconnect from all others.
		usersChanged(signaling, [], previousUsersInRoom)
		return
	}

	const newSessionIds = arrayDiff(currentUsersInRoom, previousUsersInRoom)
	const disconnectedSessionIds = arrayDiff(previousUsersInRoom, currentUsersInRoom)
	const newUsers = []
	newSessionIds.forEach(function(sessionId) {
		newUsers.push(userMapping[sessionId])
	})
	if (newUsers.length || disconnectedSessionIds.length) {
		usersChanged(signaling, newUsers, disconnectedSessionIds)
	}
}

/**
 * @param {object} signaling The signaling object
 * @param {object} _callParticipantCollection Collection with participants
 * @param {object} _localCallParticipantModel The local participant
 */
export default function initWebRtc(signaling, _callParticipantCollection, _localCallParticipantModel) {
	callParticipantCollection = _callParticipantCollection
	localCallParticipantModel = _localCallParticipantModel

	signaling.on('usersLeft', function(users) {
		if (!localUserInCall) {
			return
		}

		users.forEach(function(user) {
			delete usersInCallMapping[user]
		})
		usersChanged(signaling, [], users)
	})
	signaling.on('usersChanged', function(users) {
		if (!localUserInCall) {
			return
		}

		users.forEach(function(user) {
			const sessionId = user.sessionId || user.sessionid
			usersInCallMapping[sessionId] = user
		})
		usersInCallChanged(signaling, usersInCallMapping)
	})
	signaling.on('allUsersChangedInCallToDisconnected', function() {
		if (!localUserInCall) {
			return
		}

		// "End meeting for all" was used, we don't have a user list but everyone disconnects from the call
		usersInCallMapping = {}
		usersInCallChanged(signaling, usersInCallMapping)
	})
	signaling.on('participantFlagsChanged', function(event) {
		if (!localUserInCall) {
			return
		}

		/**
		 * event {
		 *   roomid: "1609407087",
		 *   sessionid: "…",
		 *   flags: 1
		 * }
		 */
		const callParticipantModel = callParticipantCollection.get(event.sessionid)
		if (callParticipantModel) {
			callParticipantModel.set('speaking', (event.flags & PARTICIPANT.SIP_FLAG.SPEAKING) > 0)
			callParticipantModel.set('audioAvailable', (event.flags & PARTICIPANT.SIP_FLAG.MUTE_MICROPHONE) === 0)
			callParticipantModel.set('raisedHand', {
				state: (event.flags & PARTICIPANT.SIP_FLAG.RAISE_HAND) !== 0,
				timestamp: Date.now(),
			})
		}
	})
	signaling.on('usersInRoom', function(users) {
		if (!localUserInCall) {
			return
		}

		usersInCallMapping = {}
		users.forEach(function(user) {
			const sessionId = user.sessionId || user.sessionid
			usersInCallMapping[sessionId] = user
		})
		usersInCallChanged(signaling, usersInCallMapping)
	})
	signaling.on('beforeJoinCall', function(token, reconnect) {
		// The user needs to be set as in the call before the request is
		// actually done to also cover the (unlikely) case that the request
		// takes too long to return and the associated signaling message
		// is received before the "join call" request ends.
		localUserInCall = true
	})
	signaling.on('beforeLeaveCall', function(token, reconnect) {
		// The user needs to be set as not in the call before the request is
		// actually done to also cover the (unlikely) case that the request
		// takes too long to return and the associated signaling message
		// is received before the "leave call" request ends.
		localUserInCall = false
	})
	signaling.on('leaveCall', function(token, reconnect) {
		// When the MCU is used and there is a connection error the call is
		// left and then joined again to perform the reconnection. In those
		// cases the call should be kept active from the point of view of
		// WebRTC.
		if (reconnect) {
			// The signaling state must be marked as not in the call to prevent
			// that after joining the call again the local participant is
			// treated as in the call before a signaling message confirms it.
			selfInCall = PARTICIPANT.CALL_FLAG.DISCONNECTED

			return
		}

		clearErrorNotification()
		Sounds.playLeave(true)

		// The delayed connection for the own peer needs to be explicitly
		// stopped, as the current own session is not passed along with the
		// sessions of the other participants as "disconnected" to
		// "usersChanged" when a call is left.
		// The peer, on the other hand, is ended by the calls below.
		if (ownPeer && delayedConnectionToPeer[ownPeer.id]) {
			clearInterval(delayedConnectionToPeer[ownPeer.id])
			delete delayedConnectionToPeer[ownPeer.id]
		}

		// Besides stopping the media "leaveCall" would end the peers, but it
		// does not stop the timers for pending connections, removes models or
		// clears the call data, so this needs to be explicitly done here
		// instead.
		selfInCall = PARTICIPANT.CALL_FLAG.DISCONNECTED

		usersChanged(signaling, [], previousUsersInRoom)
		usersInCallMapping = {}

		webrtc.leaveCall()
	})

	signaling.on('message', function(message) {
		if (!localUserInCall) {
			console.debug('Message received when not in the call, ignore', message.type, message)

			message.type = 'message-to-ignore'

			return
		}

		if (message.type === 'answer' && message.roomType === 'video' && delayedConnectionToPeer[message.from]) {
			clearInterval(delayedConnectionToPeer[message.from])
			delete delayedConnectionToPeer[message.from]

			return
		}

		if (message.type !== 'offer') {
			return
		}

		const peers = webrtc.webrtc.peers
		const stalePeer = peers.find(function(peer) {
			if (peer.sharemyscreen) {
				return false
			}

			return peer.id === message.from && peer.type === message.roomType && peer.sid !== message.sid
		})

		if (stalePeer) {
			stalePeer.end()
		}

		if (message.roomType === 'video' && delayedConnectionToPeer[message.from]) {
			clearInterval(delayedConnectionToPeer[message.from])
			delete delayedConnectionToPeer[message.from]
		}

		// MCU screen offers do not include the "broadcaster" property,
		// which is expected by SimpleWebRTC in screen offers from a remote
		// peer, so it needs to be explicitly added.
		if (signaling.hasFeature('mcu') && message.roomType === 'screen') {
			message.broadcaster = message.from
		}
	})

	webrtc = new SimpleWebRTC({
		autoRequestMedia: true,
		debug: false,
		autoAdjustMic: false,
		audioFallback: true,
		connection: signaling,
		enableDataChannels: true,
		enableSimulcast: signaling.hasFeature('simulcast'),
		nick: store.getters.getDisplayName(),
	})

	if (!window.OCA.Talk) {
		window.OCA.Talk = {}
	}
	window.OCA.Talk.SimpleWebRTC = webrtc

	signaling.on('pullMessagesStoppedOnFail', function() {
		// Force leaving the call in WebRTC; when pulling messages stops due
		// to failures the room is left, and leaving the room indirectly
		// runs signaling.leaveCurrentCall(), but if the signaling fails to
		// leave the call (which is likely due to the messages failing to be
		// received) no event will be triggered and the call will not be
		// left from WebRTC point of view.
		webrtc.leaveCall()
	})

	webrtc.startMedia = function(token, flags) {
		// If no flags are provided try to enable both audio and video.
		// Otherwise, try to enable only that allowed by the flags.
		const mediaConstraints = {
			audio: !flags || !!(flags & PARTICIPANT.CALL_FLAG.WITH_AUDIO),
			video: !flags || !!(flags & PARTICIPANT.CALL_FLAG.WITH_VIDEO),
		}

		webrtc.joinCall(token, mediaConstraints)
	}

	const sendDataChannelToAll = function(channel, message, payload) {
		// If running with MCU, the message must be sent through the
		// publishing peer and will be distributed by the MCU to subscribers.
		if (signaling.hasFeature && signaling.hasFeature('mcu')) {
			if (ownPeer) {
				ownPeer.sendDirectly(channel, message, payload)
			}

			return
		}
		webrtc.sendDirectlyToAll(channel, message, payload)
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function handleIceConnectionStateConnected(peer) {
		// Send the current information about the state.
		if (!signaling.hasFeature('mcu')) {
			// Only the media state needs to be sent, the nick was already sent
			// in the offer/answer.
			sendCurrentMediaState()
		} else {
			sendCurrentStateWithRepetition()
		}

		// Reset ice restart counter for peer
		if (spreedPeerConnectionTable[peer.id] > 0) {
			spreedPeerConnectionTable[peer.id] = 0
		}
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function handleIceConnectionStateDisconnected(peer) {
		setTimeout(function() {
			if (peer.pc.iceConnectionState !== 'disconnected') {
				return
			}

			peer.emit('extendedIceConnectionStateChange', 'disconnected-long')

			if (!signaling.hasFeature('mcu')) {
				// Disconnections are not handled with the MCU, only
				// failures.

				// If the peer is still disconnected after 5 seconds we try
				// ICE restart.
				if (spreedPeerConnectionTable[peer.id] < 5) {
					if (peer.pc.localDescription.type === 'offer'
						&& peer.pc.signalingState === 'stable') {
						spreedPeerConnectionTable[peer.id]++
						console.debug('ICE restart after disconnect.', peer.id, peer)
						peer.icerestart()
					}
				}
			}
		}, 5000)
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function handleIceConnectionStateFailed(peer) {
		if (!showedTURNWarning && !signaling.settings.turnservers.length) {
			showError(
				t('spreed', 'Could not establish a connection with at least one participant. A TURN server might be needed for your scenario. Please ask your administrator to set one up following {linkstart}this documentation{linkend}.')
					.replace('{linkstart}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://nextcloud-talk.readthedocs.io/en/latest/TURN/">')
					.replace('{linkend}', ' ↗</a>'),
				{
					timeout: TOAST_PERMANENT_TIMEOUT,
					isHTML: true,
				},
			)
			showedTURNWarning = true
		}

		if (!signaling.hasFeature('mcu')) {
			if (spreedPeerConnectionTable[peer.id] < 5) {
				if (peer.pc.localDescription.type === 'offer'
					&& peer.pc.signalingState === 'stable') {
					spreedPeerConnectionTable[peer.id]++
					console.debug('ICE restart after failure.', peer.id, peer)
					peer.icerestart()
				}
			} else {
				console.error('ICE failed after 5 tries.', peer.id, peer)

				peer.emit('extendedIceConnectionStateChange', 'failed-no-restart')
			}
		} else {
			// This handles ICE failures of a receiver peer; ICE failures of
			// the sender peer are handled in the "iceFailed" event.
			console.debug('Request offer again', peer.id, peer)

			signaling.requestOffer(peer.id, 'video')

			clearInterval(delayedConnectionToPeer[peer.id])

			delayedConnectionToPeer[peer.id] = setInterval(function() {
				console.debug('No offer received, request offer again', peer.id, peer)

				signaling.requestOffer(peer.id, 'video')
			}, 10000)
		}
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function setHandlerForIceConnectionStateChange(peer) {
		// Initialize ice restart counter for peer
		spreedPeerConnectionTable[peer.id] = 0

		peer.pc.addEventListener('iceconnectionstatechange', function() {
			peer.emit('extendedIceConnectionStateChange', peer.pc.iceConnectionState)

			switch (peer.pc.iceConnectionState) {
				case 'checking':
					console.debug('Connecting to peer...', peer.id, peer)

					break
				case 'connected':
				case 'completed': // on caller side
					console.debug('Connection established.', peer.id, peer)

					handleIceConnectionStateConnected(peer)
					break
				case 'disconnected':
					console.debug('Disconnected.', peer.id, peer)

					handleIceConnectionStateDisconnected(peer)
					break
				case 'failed':
					console.debug('Connection failed.', peer.id, peer)

					handleIceConnectionStateFailed(peer)
					break
				case 'closed':
					console.debug('Connection closed.', peer.id, peer)

					break
			}
		})
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function setHandlerForConnectionStateChange(peer) {
		peer.pc.addEventListener('connectionstatechange', function() {
			if (peer.pc.connectionState !== 'failed') {
				return
			}

			if (peer.pc.iceConnectionState === 'failed') {
				return
			}

			// Work around Chromium bug where "iceConnectionState" never changes
			// to "failed" (it stays as "disconnected"). When that happens
			// "connectionState" actually does change to "failed", so the normal
			// handling of "iceConnectionState === failed" is triggered here.

			peer.emit('extendedIceConnectionStateChange', peer.pc.connectionState)

			handleIceConnectionStateFailed(peer)
		})
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function setHandlerForSignalingStateChange(peer) {
		peer.pc.addEventListener('signalingstatechange', function() {
			peer.emit('signalingStateChange', peer.pc.signalingState)
		})
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function setHandlerForOwnIceConnectionStateChange(peer) {
		peer.pc.addEventListener('iceconnectionstatechange', function() {
			peer.emit('extendedIceConnectionStateChange', peer.pc.iceConnectionState)

			switch (peer.pc.iceConnectionState) {
				case 'checking':
					console.debug('Connecting own peer...', peer.id, peer)

					break
				case 'connected':
				case 'completed':
					console.debug('Connection established (own peer).', peer.id, peer)

					break
				case 'disconnected':
					console.debug('Disconnected (own peer).', peer.id, peer)

					setTimeout(function() {
						if (peer.pc.iceConnectionState !== 'disconnected') {
							return
						}

						peer.emit('extendedIceConnectionStateChange', 'disconnected-long')
					}, 5000)
					break
				case 'failed':
					console.debug('Connection failed (own peer).', peer.id, peer)

					break
				case 'closed':
					console.debug('Connection closed (own peer).', peer.id, peer)

					break
			}
		})
	}

	const forceReconnect = function(signaling, flags) {
		if (ownPeer) {
			if (delayedConnectionToPeer[ownPeer.id]) {
				clearInterval(delayedConnectionToPeer[ownPeer.id])
				delete delayedConnectionToPeer[ownPeer.id]
			}
			ownPeer.end()
		}

		usersChanged(signaling, [], previousUsersInRoom)
		usersInCallMapping = {}

		// Reconnects with a new session id will trigger "usersChanged"
		// with the users in the room and that will re-establish the
		// peerconnection streams.
		// If flags are undefined the current call flags are used.
		signaling.forceReconnect(true, flags)
	}

	/**
	 * @param {object} peer The peer connection to handle the state on
	 */
	function setHandlerForNegotiationNeeded(peer) {
		peer.pc.addEventListener('negotiationneeded', function() {
			// When the HPB is used and the negotiation is needed for a receiver
			// peer (for example, to block the received video) there is no need
			// to force a full reconnection, it is enough to reconnect only that
			// peer.
			if (signaling.hasFeature('mcu') && peer.id !== signaling.getSessionId()) {
				// If possible update connection rather than creating a new one.
				let update = signaling.hasFeature('update-sdp')

				// Create a connection if the current one has failed, as it
				// would require an ICE restart rather than update to recover.
				if (update && (peer.pc.iceConnectionState === 'failed' || peer.pc.connectionState === 'failed')) {
					update = false
				}

				// If the connection needs to be updated but a new connection
				// (or another update) is already pending ignore the new update.
				// If a new connection needs to be created rather than updated
				// then force it even if there is another one already pending.
				if (update && delayedConnectionToPeer[peer.id]) {
					return
				}

				signaling.requestOffer(peer.id, 'video', update ? peer.sid : undefined)

				clearInterval(delayedConnectionToPeer[peer.id])

				delayedConnectionToPeer[peer.id] = setInterval(function() {
					console.debug('No offer received, request offer again' + update ? '(update)' : '', peer.id, peer)

					signaling.requestOffer(peer.id, 'video', update ? peer.sid : undefined)
				}, 10000)

				return
			}

			// Negotiation needed will be first triggered before the connection
			// is established, but forcing a reconnection should be done only
			// once the connection was established.
			if (peer.pc.iceConnectionState !== 'new' && peer.pc.iceConnectionState !== 'checking') {
				// Update the media flags if needed, as the renegotiation could
				// have been caused by tracks being added or removed.
				const audioSender = peer.pc.getSenders().find((sender) => (sender.track && sender.track.kind === 'audio') || (sender.trackDisabled && sender.trackDisabled.kind === 'audio'))
				const videoSender = peer.pc.getSenders().find((sender) => (sender.track && sender.track.kind === 'video') || (sender.trackDisabled && sender.trackDisabled.kind === 'video'))

				let flags = signaling.getCurrentCallFlags()
				if (audioSender) {
					flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
				} else {
					flags &= ~PARTICIPANT.CALL_FLAG.WITH_AUDIO
				}
				if (videoSender) {
					flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
				} else {
					flags &= ~PARTICIPANT.CALL_FLAG.WITH_VIDEO
				}

				// Negotiation is expected to be needed only when a new track is
				// added to or removed from a peer. Therefore if the HPB is used
				// the negotiation will be needed in the own peer, but if the
				// HPB is not used it will be needed in all peers. However, in
				// that case as soon as the forced reconnection is triggered all
				// the peers will be cleared, so in practice there will be just
				// one forced reconnection even if there are several peers.
				// FIXME: despite all of the above this is a dirty and ugly hack
				// that should be fixed with proper renegotiation.
				forceReconnect(signaling, flags)
			}
		})
	}

	const reconnectOnPermissionsChange = (users) => {
		const currentParticipant = users.find((user) => {
			const sessionId = user.sessionId || user.sessionid
			return sessionId === signaling.getSessionId()
		})

		if (!currentParticipant) {
			return
		}

		if (!currentParticipant.inCall) {
			return
		}

		if (currentParticipant.participantPermissions === undefined) {
			return
		}

		if (webrtc.webrtc.isAudioAllowed() === !!(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO)
			&& webrtc.webrtc.isVideoAllowed() === !!(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO)) {
			return
		}

		let hasAudioSenders = false
		let hasVideoSenders = false

		webrtc.webrtc.getPeers(null, 'video').forEach((peer) => {
			// Look for any sender of each kind, even if the sender no longer
			// has a track attached to it.
			const audioSender = peer.pc.getSenders().find((sender) => sender.kind === 'audio' || (sender.track && sender.track.kind === 'audio') || (sender.trackDisabled && sender.trackDisabled.kind === 'audio'))
			const videoSender = peer.pc.getSenders().find((sender) => sender.kind === 'video' || (sender.track && sender.track.kind === 'video') || (sender.trackDisabled && sender.trackDisabled.kind === 'video'))

			hasAudioSenders ||= !!audioSender
			hasVideoSenders ||= !!videoSender
		})

		const removeSender = (hasAudioSenders && !(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO))
			|| (hasVideoSenders && !(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO))

		if (currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) {
			webrtc.webrtc.allowAudio()
		} else {
			webrtc.webrtc.disallowAudio()
		}

		if (currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO) {
			webrtc.webrtc.allowVideo()
		} else {
			webrtc.webrtc.disallowVideo()
		}

		if (webrtc.webrtc.isLocalMediaActive()
			&& !(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO)
			&& !(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO)) {
			webrtc.stopLocalVideo()

			// If the MCU is used and there is no sending peer there is no need
			// to force a reconnection, as there will be no connection that
			// needs to be stopped.
			if (!signaling.hasFeature('mcu') || ownPeer) {
				forceReconnect(signaling, PARTICIPANT.CALL_FLAG.IN_CALL)
			}

			return
		}

		// If a sender kind is no longer allowed a forced reconnection needs to
		// be explicitly triggered. Otherwise "removing" the no longer allowed
		// track will just set it to null in the sender, which does not trigger
		// a "negotiationneeded" event and thus an automatic forced
		// reconnection.
		if (webrtc.webrtc.isLocalMediaActive() && removeSender) {
			let flags = signaling.getCurrentCallFlags()
			if (!(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO)) {
				flags &= ~PARTICIPANT.CALL_FLAG.WITH_AUDIO
			}
			if (!(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO)) {
				flags &= ~PARTICIPANT.CALL_FLAG.WITH_VIDEO
			}

			// The flags may be updated later if, besides removing a sender, a
			// track is also added (for example, when there are both a
			// microphone and a camera and audio permissions are removed at the
			// same time that video permissions are added). However, at this
			// point it is not possible to know if that will happen (getting the
			// new track is an async operation and it could fail), so the flags
			// are updated only with the known values.
			forceReconnect(signaling, flags)

			return
		}

		// If media is already active and a track is added "negotiationneeded"
		// will be triggered, which in turn will automatically force a
		// reconnection.
		if (webrtc.webrtc.isLocalMediaActive()) {
			return
		}

		// If media is not active but the participant does not have publishing
		// permissions there is no need to start the media nor reconnect.
		if (!(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO)
			&& !(currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO)) {
			return
		}

		const forceReconnectOnceLocalMediaStarted = (constraints) => {
			webrtc.off('localMediaStarted', forceReconnectOnceLocalMediaStarted)
			webrtc.off('localMediaError', forceReconnectOnceLocalMediaError)

			let flags = PARTICIPANT.CALL_FLAG.IN_CALL
			if (constraints) {
				if (constraints.audio) {
					flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
				}
				if (constraints.video && signaling.getSendVideoIfAvailable()) {
					flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
				}
			}

			forceReconnect(signaling, flags)
		}
		const forceReconnectOnceLocalMediaError = () => {
			webrtc.off('localMediaStarted', forceReconnectOnceLocalMediaStarted)
			webrtc.off('localMediaError', forceReconnectOnceLocalMediaError)

			// If the media fails to start there will be no media, so no need to
			// reconnect. A reconnection will happen once the user selects a
			// different device.
		}

		webrtc.on('localMediaStarted', forceReconnectOnceLocalMediaStarted)
		webrtc.on('localMediaError', forceReconnectOnceLocalMediaError)

		const constraints = {
			audio: currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO,
			video: currentParticipant.participantPermissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO,
		}
		webrtc.startLocalVideo(constraints)
	}

	signaling.on('usersInRoom', function(users) {
		reconnectOnPermissionsChange(users)
	})
	signaling.on('usersChanged', function(users) {
		reconnectOnPermissionsChange(users)
	})

	webrtc.on('createdPeer', function(peer) {
		console.debug('Peer created', peer.id, peer)

		if (peer.id !== signaling.getSessionId() && !peer.sharemyscreen) {
			// In some strange cases a Peer can be added before its
			// participant is found in the list of participants.
			let callParticipantModel = callParticipantCollection.get(peer.id)
			if (!callParticipantModel) {
				callParticipantModel = callParticipantCollection.add({
					peerId: peer.id,
					webRtc: webrtc,
				})
			}

			if (peer.type === 'video') {
				callParticipantModel.setPeer(peer)
			} else {
				callParticipantModel.setScreenPeer(peer)
			}

			// The SIP bridge publisher does not have data channels, so they
			// need to be explicitly disabled in the subscriber. Otherwise it
			// would try to open them, which would cause an endless loop of
			// renegotiations, as after a negotiation the data channels will
			// still not be opened, which will trigger a negotiation again.
			if (callParticipantModel.get('internal')) {
				peer.enableDataChannels = false
			}
		}

		if (peer.type === 'video') {
			if (peer.id === signaling.getSessionId()) {
				setHandlerForOwnIceConnectionStateChange(peer)
			} else {
				setHandlerForIceConnectionStateChange(peer)
				setHandlerForConnectionStateChange(peer)
				setHandlerForSignalingStateChange(peer)
			}

			setHandlerForNegotiationNeeded(peer)

			// Make sure required data channels exist for all peers (that have
			// not disabled them). This is required for peers that get created
			// by SimpleWebRTC from received "Offer" messages. Otherwise the
			// "channelMessage" will not be called.
			peer.getDataChannel('status')
		}
	})

	/**
	 * @param {object} peer The peer connection to check media on
	 * @param {object} track The track to check the media on
	 * @param {string} mediaType "video" or "audio"
	 */
	function checkPeerMedia(peer, track, mediaType) {
		return new Promise((resolve, reject) => {
			peer.pc.getStats(track).then(function(stats) {
				let result = false
				stats.forEach(function(statsReport) {
					if (result || statsReport.mediaType !== mediaType || !Object.prototype.hasOwnProperty.call(statsReport, 'bytesReceived')) {
						return
					}

					if (statsReport.bytesReceived > 0) {
						if (mediaType === 'video' && statsReport.bytesReceived < 2000) {
							// A video with less than 2000 bytes is an empty single frame of the MCU
							// console.debug('Participant is registered with with video but didn\'t send a lot of data, so we assume the video is disabled for now.')
							result = true
							return
						}
						webrtc.emit('unmute', {
							id: peer.id,
							name: mediaType,
						})
						result = true
					}
				})
				if (result) {
					resolve()
				} else {
					reject(new Error('No bytes received'))
				}
			})
		})
	}

	/**
	 * @param {object} peer The peer connection to check media on
	 */
	function stopPeerCheckAudioMedia(peer) {
		clearInterval(peer.check_audio_interval)
		peer.check_audio_interval = null
	}

	/**
	 * @param {object} peer The peer connection to check media on
	 */
	function stopPeerCheckVideoMedia(peer) {
		clearInterval(peer.check_video_interval)
		peer.check_video_interval = null
	}

	/**
	 * @param {string} peerId ID to get the peer for
	 * @param {string} mediaType "video" or "audio"
	 */
	function stopPeerIdCheckMediaType(peerId, mediaType) {
		// There should be just one video peer with that id, but iterating is
		// safer.
		const peers = webrtc.getPeers(peerId, 'video')
		peers.forEach(function(peer) {
			if (mediaType === 'audio') {
				stopPeerCheckAudioMedia(peer)
			} else if (mediaType === 'video') {
				stopPeerCheckVideoMedia(peer)
			}
		})
	}

	if (signaling.hasFeature('mcu')) {
		webrtc.on('mute', function(data) {
			stopPeerIdCheckMediaType(data.id, data.name)
		})
		webrtc.on('unmute', function(data) {
			stopPeerIdCheckMediaType(data.id, data.name)
		})
	}

	/**
	 * @param {object} peer The peer connection to check media on
	 */
	function stopPeerCheckMedia(peer) {
		stopPeerCheckAudioMedia(peer)
		stopPeerCheckVideoMedia(peer)
	}

	/**
	 * @param {object} peer The peer connection to check media on
	 * @param {object} stream The stream to check
	 */
	function startPeerCheckMedia(peer, stream) {
		stopPeerCheckMedia(peer)
		peer.check_video_interval = setInterval(function() {
			stream.getVideoTracks().forEach(function(video) {
				checkPeerMedia(peer, video, 'video').then(function() {
					stopPeerCheckVideoMedia(peer)
				}).catch(() => {
				})
			})
		}, 1000)
		peer.check_audio_interval = setInterval(function() {
			stream.getAudioTracks().forEach(function(audio) {
				checkPeerMedia(peer, audio, 'audio').then(function() {
					stopPeerCheckAudioMedia(peer)
				}).catch(() => {
				})
			})
		}, 1000)
	}

	webrtc.on('peerStreamAdded', function(peer) {
		// With the MCU, a newly subscribed stream might not get the
		// "audioOn"/"videoOn" messages as they are only sent when
		// a user starts publishing. Instead wait for initial data
		// and trigger events locally.
		if (!signaling.hasFeature('mcu')) {
			return
		}

		if (peer.type === 'screen') {
			return
		}

		startPeerCheckMedia(peer, peer.stream)
	})

	webrtc.on('peerStreamRemoved', function(peer) {
		stopPeerCheckMedia(peer)
	})

	webrtc.on('peerEnded', function(peer) {
		if (ownPeer === peer) {
			ownPeer = null

			localCallParticipantModel.setPeer(ownPeer)
		}

		if (ownScreenPeer === peer) {
			ownScreenPeer = null

			localCallParticipantModel.setScreenPeer(ownScreenPeer)
		}
	})

	webrtc.webrtc.on('videoOn', function() {
		if (signaling.getSendVideoIfAvailable()) {
			return
		}

		// When enabling the local video if the video is not being sent a
		// reconnection is forced to start sending it.
		signaling.setSendVideoIfAvailable(true)

		let flags = signaling.getCurrentCallFlags()
		flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO

		forceReconnect(signaling, flags)
	})

	webrtc.webrtc.on('iceFailed', function(/* peer */) {
		if (!signaling.hasFeature('mcu')) {
			// ICE restarts will be handled by "iceConnectionStateChange"
			// above.
			return
		}

		// For now assume the connection to the MCU is interrupted on ICE
		// failures and force a reconnection of all streams.
		forceReconnect(signaling)
	})

	let localStreamRequestedTimeout = null
	let localStreamRequestedTimeoutNotification = null

	let errorNotificationHandle = null

	const clearLocalStreamRequestedTimeoutAndHideNotification = function() {
		clearTimeout(localStreamRequestedTimeout)
		localStreamRequestedTimeout = null

		if (localStreamRequestedTimeoutNotification) {
			localStreamRequestedTimeoutNotification.hideToast()
			localStreamRequestedTimeoutNotification = null
		}
	}

	const clearErrorNotification = function() {
		if (errorNotificationHandle) {
			errorNotificationHandle.hideToast()
			errorNotificationHandle = null
		}
	}

	// In some cases the browser may enter in a faulty state in which
	// "getUserMedia" does not return neither successfully nor with an
	// error. It is not possible to detect this except by guessing when some
	// time passes and the user has not granted nor rejected the media
	// permissions.
	webrtc.on('localStreamRequested', function() {
		clearLocalStreamRequestedTimeoutAndHideNotification()

		localStreamRequestedTimeout = setTimeout(function() {
			// FIXME emit an event and handle it as needed instead of
			// calling UI code from here.
			localStreamRequestedTimeoutNotification = showError(t('spreed', 'This is taking longer than expected. Are the media permissions already granted (or rejected)? If yes please restart your browser, as audio and video are failing'), {
				timeout: TOAST_PERMANENT_TIMEOUT,
			})
		}, 10000)
	})

	signaling.on('leaveRoom', function(token) {
		if (signaling.currentRoomToken === token) {
			clearLocalStreamRequestedTimeoutAndHideNotification()
			clearErrorNotification()
		}
	})

	/**
	 * Return the appropriate call flags for the current local tracks.
	 *
	 * @return {number} a bitwise combination of call flags.
	 */
	function getCallFlagsFromLocalMedia() {
		let callFlags = PARTICIPANT.CALL_FLAG.IN_CALL

		if (webrtc.webrtc.hasAudioTrack()) {
			callFlags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
		}
		if (webrtc.webrtc.hasVideoTrack()) {
			callFlags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
		}

		return callFlags
	}

	signaling.on('joinCall', function(token) {
		const expectedCallFlags = getCallFlagsFromLocalMedia()

		// If the current call flags do not match the expected ones (for
		// example, if a new track is added during a forced reconnection the
		// update would fail, and once joining the call finishes the flags for
		// that new track would not be set) they need to be updated.
		if (signaling.getCurrentCallFlags() === expectedCallFlags) {
			return
		}

		// The other participants may not establish a connection if the original
		// flags were just IN_CALL, so a forced reconnection needs to be
		// triggered in that case.
		if (signaling.getCurrentCallFlags() === PARTICIPANT.CALL_FLAG.IN_CALL) {
			forceReconnect(signaling, expectedCallFlags)

			return
		}

		signaling.updateCurrentCallFlags(expectedCallFlags)
	})

	/**
	 * Return whether there are sender peers, either already created or about to
	 * be created (if there is a pending connection).
	 *
	 * If the MCU is used then there will be a single sender peer (the own
	 * peer). Otherwise every peer is both a sender and a receiver peer.
	 *
	 * @return {boolean} true if there are sender peers, false otherwise.
	 */
	function hasSenderPeers() {
		if (signaling.hasFeature('mcu')) {
			return !!ownPeer
		}

		return webrtc.webrtc.getPeers(null, 'video').length > 0 || Object.keys(delayedConnectionToPeer).length > 0
	}

	webrtc.on('localTrackReplaced', function(newTrack, oldTrack/* , stream */) {
		const callFlags = getCallFlagsFromLocalMedia()

		// A reconnection is not needed if a device is disabled or if there are
		// no other participants in the call. Even if there are other
		// participants a reconnection is not needed if there are already sender
		// peers (as "negotiationneeded" will be automatically triggered by them
		// if needed, which will cause the reconnection). Only if there are no
		// sender peers or there are, but the previous call flags were just "in
		// call", a reconnection is needed to ensure that the other participants
		// will try to connect with the local one.
		if (newTrack && previousUsersInRoom.length > 0 && (!hasSenderPeers() || signaling.getCurrentCallFlags() === PARTICIPANT.CALL_FLAG.IN_CALL)) {
			forceReconnect(signaling, callFlags)

			return
		}

		if (signaling.getCurrentCallFlags() !== callFlags) {
			signaling.updateCurrentCallFlags(callFlags)
		}
	})

	webrtc.on('localMediaStarted', function(/* configuration */) {
		console.info('localMediaStarted')

		clearLocalStreamRequestedTimeoutAndHideNotification()

		if (signaling.hasFeature('mcu')) {
			checkStartPublishOwnPeer(signaling)
		}
	})

	webrtc.on('localMediaError', function(error) {
		console.warn('Access to microphone & camera failed', error)

		clearLocalStreamRequestedTimeoutAndHideNotification()

		if (error.name === 'TypeError') {
			// Both audio and video were explicitly disabled, no need to show an
			// error.
			return
		}

		let message
		let timeout = TOAST_PERMANENT_TIMEOUT
		if ((error.name === 'NotSupportedError'
			&& webrtc.capabilities.supportRTCPeerConnection)
		|| (error.name === 'NotAllowedError'
			&& error.message && error.message.includes('Only secure origins'))) {
			message = t('spreed', 'Access to microphone & camera is only possible with HTTPS')
			message += ': ' + t('spreed', 'Please move your setup to HTTPS')
		} else if (error.name === 'NotAllowedError') {
			message = t('spreed', 'Access to microphone & camera was denied')
			timeout = TOAST_DEFAULT_TIMEOUT
		} else if (!webrtc.capabilities.support) {
			console.error('WebRTC not supported')

			message = t('spreed', 'WebRTC is not supported in your browser')
			message += ': ' + t('spreed', 'Please use a different browser like Firefox or Chrome')
		} else {
			// Mostly happens in Chrome (NotFoundError): when no audio device available
			// not sure what else can cause this
			message = t('spreed', 'Error while accessing microphone & camera')
			console.error('Error while accessing microphone & camera: ', error.message, error.name)
		}

		errorNotificationHandle = showError(message, {
			timeout,
		})
	})

	webrtc.on('channelOpen', function(channel) {
		console.debug('%s datachannel is open', channel.label)
	})

	webrtc.on('channelMessage', function(peer, label, data) {
		if (data.type === 'audioOn') {
			webrtc.emit('unmute', { id: peer.id, name: 'audio' })
		} else if (data.type === 'audioOff') {
			webrtc.emit('mute', { id: peer.id, name: 'audio' })
		} else if (data.type === 'videoOn') {
			webrtc.emit('unmute', { id: peer.id, name: 'video' })
		} else if (data.type === 'videoOff') {
			webrtc.emit('mute', { id: peer.id, name: 'video' })
		} else if (data.type === 'nickChanged') {
			const name = typeof (data.payload) === 'string' ? data.payload : data.payload.name
			webrtc.emit('nick', { id: peer.id, name })
		} else if (data.type === 'speaking' || data.type === 'stoppedSpeaking') {
			// Valid known messages, handled by CallParticipantModel.js
		} else {
			console.debug('Unknown message type %s from %s datachannel', data.type, label, data, peer.id, peer)
		}
	})

	webrtc.on('sendToAll', function(messageType, payload) {
		for (const sessionId in usersInCallMapping) {
			if (!usersInCallMapping[sessionId].inCall) {
				continue
			} else if (sessionId === signaling.getSessionId()) {
				continue
			}

			// "roomType" is not really relevant without a peer or when
			// referring to the whole participant, but it is nevertheless
			// expected in the message. As most of the signaling messages
			// currently sent to all participants are related to audio/video
			// state "video" is used as the room type.
			const message = {
				to: sessionId,
				roomType: 'video',
				type: messageType,
				payload,
			}
			signaling.emit('message', message)
		}
	})

	// Send the speaking status events via data channel
	webrtc.on('speaking', function() {
		sendDataChannelToAll('status', 'speaking')
	})
	webrtc.on('stoppedSpeaking', function() {
		sendDataChannelToAll('status', 'stoppedSpeaking')
	})

	// Send the audio on and off events via data channel
	webrtc.on('audioOn', function() {
		sendDataChannelToAll('status', 'audioOn')
	})
	webrtc.on('audioOff', function() {
		sendDataChannelToAll('status', 'audioOff')
	})
	webrtc.on('videoOn', function() {
		sendDataChannelToAll('status', 'videoOn')
	})
	webrtc.on('videoOff', function() {
		sendDataChannelToAll('status', 'videoOff')
	})

	// Send the nick changed event via data channel and signaling
	//
	// The message format is different in each case. Due to historical reasons
	// the payload of the data channel message is either a string that contains
	// the name (if the participant is a guest) or an object with "name" and
	// "userid" string fields (when the participant is a user).
	//
	// In the newer signaling message, on the other hand, the payload is always
	// an object with only a "name" string field.
	webrtc.on('nickChanged', function(name) {
		let payload
		if (signaling.settings.userId === null) {
			payload = name
		} else {
			payload = {
				name,
				userid: signaling.settings.userId,
			}
		}

		sendDataChannelToAll('status', 'nickChanged', payload)

		webrtc.sendToAll('nickChanged', { name })
	})

	// Local screen added.
	webrtc.on('localScreenAdded', function() {
		const currentSessionId = signaling.getSessionId()
		for (const sessionId in usersInCallMapping) {
			if (!Object.prototype.hasOwnProperty.call(usersInCallMapping, sessionId)) {
				continue
			} else if (!usersInCallMapping[sessionId].inCall) {
				continue
			} else if (sessionId === currentSessionId) {
				// Running with MCU, no need to create screensharing
				// subscriber for client itself.
				continue
			}

			createScreensharingPeer(signaling, sessionId)
		}
	})

	webrtc.on('localScreenStopped', function() {
		if (!signaling.hasFeature('mcu')) {
			// Only need to notify clients here if running with MCU.
			// Otherwise SimpleWebRTC will notify each client on its own.
			return
		}

		signaling.sendRoomMessage({
			roomType: 'screen',
			type: 'unshareScreen',
		})
	})

	webrtc.on('disconnected', function() {
		if (ownPeer) {
			if (delayedConnectionToPeer[ownPeer.id]) {
				clearInterval(delayedConnectionToPeer[ownPeer.id])
				delete delayedConnectionToPeer[ownPeer.id]
			}

			ownPeer.end()
		}

		if (ownScreenPeer) {
			ownScreenPeer.end()
		}

		selfInCall = PARTICIPANT.CALL_FLAG.DISCONNECTED

		usersChanged(signaling, [], previousUsersInRoom)
		usersInCallMapping = {}
	})

	return webrtc
}
