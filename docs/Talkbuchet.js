/**
 *
 * @copyright Copyright (c) 2021, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * HOW TO SETUP:
 * -----------------------------------------------------------------------------
 * - Set the right values in "talkOcsApiUrl", "user" and "password" (a user must
 *   be used; guests do not work).
 * - If HPB clustering is enabled, set the token of a conversation in "token"
 *   (otherwise leave empty).
 * - Set whether to use audio, video or both in the "getUserMedia" call.
 * - Set the desired numbers in "publishersCount" and
 *   "subscribersPerPublisherCount" (in a regular call with N participants you
 *   would have N publishers and N-1 subscribers).
 *
 * HOW TO RUN:
 * -----------------------------------------------------------------------------
 * - In the browser, log in the Nextcloud server (with the same user as in this
 *   script).
 * - Copy and paste the full script in the console of the browser to run it.
 * - To run it again execute "closeConnections()" in the console; then you must
 *   reload the page and copy and paste the script again.
 *
 * HOW TO ENABLE AND DISABLE THE MEDIA DURING A TEST:
 * -----------------------------------------------------------------------------
 * You can manually enable and disable the media during a test by copying and
 * pasting in the browser console the following commands:
 * - For audio:
 * stream.getAudioTracks()[0].enabled = TRUE_OR_FALSE
 * - For video:
 * stream.getVideoTracks()[0].enabled = TRUE_OR_FALSE
 *
 * Note that you can only enable and disable the original media specified in the
 * "getUserMedia" call.
 *
 * HOW TO CALIBRATE:
 * -----------------------------------------------------------------------------
 * The script starts as many publishers and subscribers for each publisher as
 * specified, each one being a real and independent peer connection to Janus.
 * Therefore, how many peer connections can be established with a single script
 * run depends on the client machine, both its CPU and its network.
 *
 * You should first "calibrate" the client by running it with different numbers
 * of publishers and subscribers to check how many peer connections are
 * supported before doing the actual stress test on Janus.
 *
 * It is most likely that the CPU or network of a single client will be
 * saturated before Janus is. The script will write to the console when a peer
 * could not be connected or if it was disconnected after being connected; if
 * there is a large number of those messages or the CPU consumption is very high
 * the client has probably reached its limit.
 *
 * Besides the messages written by the script itself you can manually check the
 * connection state by copying and pasting in the browser console the following
 * commands:
 * - For the publishers:
 * Object.values(publishers).forEach(publisher => { console.log(publisher.peerConnection.iceConnectionState) })
 * - For the subscribers:
 * subscribers.forEach(subscriber => { console.log(subscriber.peerConnection.iceConnectionState) })
 *
 * DISCLAIMER:
 * -----------------------------------------------------------------------------
 * Talk performs some optimizations during calls, like reducing the video
 * quality based on the number of participants. This script does not take into
 * account those things; it is meant to be used for stress tests of Janus rather
 * than to accurately simulate Talk behaviour.
 *
 * Independently of that, please note that an accurate simulation would not be
 * possible given that a single client has to behave as several different
 * clients. In a real call each client could have a different behaviour (not
 * only due to sending different media streams, but also from having different
 * CPU and network conditions), and that might even also affect Janus and the
 * server regarding very low level things (but relevant for performance on
 * highly loaded systems) like caches.
 *
 * Nevertheless, if those things are kept in mind the script can be anyway used
 * as a rough performance test of specific call scenarios. Just remember that in
 * regular calls peer connections increase quadratically with the number of
 * participants; specifically, publishers increase linearly while subscribers
 * increase quadratically.
 *
 * For example a call between 10 participants has 10 publishers and 90
 * subscribers (9 for each publisher) for a total of 100 peer connections, while
 * a call between 30 participants has 30 publishers and 870 subscribers (29 for
 * each publisher) for a total of 900 peer connections.

 * Due to this, if you have several clients that can only withstand ~100 peer
 * connections each in order to simulate a 30 participants call you could run
 * the script with 3 publishers and 29 subscribers per publisher on 10 clients
 * at the same time.
 */

talkOcsApiUrl = 'https://cloud.example.tld/ocs/v2.php/apps/spreed/api/'
signalingSettingsUrl = talkOcsApiUrl + 'v2/signaling/settings'
signalingBackendUrl = talkOcsApiUrl + 'v2/signaling/backend'

// Guest users do not currently work, as they must join the conversation to not
// be kicked out from the signaling server. However, joining the conversation
// a second time causes the first guest to be unregistered.
// Regular users do not need to join the conversation, so the same user can be
// connected several times to the HPB.
user = ''
password = ''

// The conversation token is only strictly needed for guests or if HPB
// clustering is enabled.
token = ''

joinRoomUrl = talkOcsApiUrl + 'v3/room/' + token + '/participants/active'

async function getSignalingSettings(user, password, token) {
	const fetchOptions = {
		headers: {
			'OCS-ApiRequest': true,
			'Accept': 'json',
		},
	}

	if (user) {
		fetchOptions.headers['Authorization'] = 'Basic ' + btoa(user + ':' + password)
	}

	const signalingSettingsResponse = await fetch(signalingSettingsUrl + '?token=' + token, fetchOptions)
	const signalingSettings = await signalingSettingsResponse.json()

	return signalingSettings.ocs.data
}

/**
 * Helper class to interact with the signaling server.
 *
 * A new signaling session is started when a Signaling object is created.
 * Messages can be sent using the sendXXX methods. Received messages are emitted
 * using events, so a listener for the type of received message can be set to
 * listen to them; the message data is provided in the "detail" attribute of the
 * event.
 */
class Signaling extends EventTarget {
	constructor(user, signalingSettings) {
		super();

		this.user = user
		this.signalingTicket = signalingSettings.ticket

		let resolveSessionId
		this.sessionId = new Promise((resolve, reject) => {
			resolveSessionId = resolve
		})

		const signalingUrl = this.sanitizeSignalingUrl(signalingSettings.server)

		this.socket = new WebSocket(signalingUrl)
		this.socket.onopen = () => {
			this.sendHello()
		}
		this.socket.onmessage = event => {
			const data = JSON.parse(event.data)

			this.dispatchEvent(new CustomEvent(data.type, { detail: data[data.type] }))
		}
		this.socket.onclose = () => {
			console.warn('Socket closed')
		}

		this.addEventListener('hello', async event => {
			const hello = event.detail
			const sessionId = hello.sessionid

			this.sessionId = sessionId
			resolveSessionId(sessionId)

			if (!user) {
				// If the current user is a guest the room needs to be joined,
				// as guests are kicked out if they just open a session in the
				// signaling server.
				this.joinRoom()
			}
		})

		this.addEventListener('error', event => {
			const error = event.detail

			console.warn(error)
		})
	}

	sanitizeSignalingUrl(url) {
		if (url.indexOf('https://') === 0) {
			url = 'wss://' + url.substr(8)
		} else if (url.indexOf('http://') === 0) {
			url = 'ws://' + url.substr(7)
		}
		if (url[url.length - 1] === '/') {
			url = url.substr(0, url.length - 1)
		}

		return url + '/spreed'
	}

	/**
	 * Returns the session ID.
	 *
	 * It can return either the actual session ID or a promise that is fulfilled
	 * once the session ID is available. Therefore this should be called with
	 * something like "sessionId = await signaling.getSessionId()".
	 */
	async getSessionId() {
		return this.sessionId
	}
	
	send(message) {
		this.socket.send(JSON.stringify(message))
	}

	sendHello() {
		this.send({
			'type': 'hello',
			'hello': {
				'version': '1.0',
				'auth': {
					'url': signalingBackendUrl,
					'params': {
						'userid': this.user,
						'ticket': this.signalingTicket,
					},
				},
			},
		})
	}

	sendMessage(data) {
        this.send({
			'type': 'message',
			'message': {
				'recipient': {
					'type': 'session',
					'sessionid': data.to,
				},
				'data': data
			}
		})
	}

	sendRequestOffer(publisherSessionId) {
		this.send({
			'type': 'message',
			'message': {
				'recipient': {
					'type': 'session',
					'sessionid': publisherSessionId,
				},
				'data': {
					'type': 'requestoffer',
					'roomType': 'video',
				},
			},
		})
	}

	async joinRoom() {
		const fetchOptions = {
			headers: {
				'OCS-ApiRequest': true,
				'Accept': 'json',
			},
			method: 'POST',
		}

		const joinRoomResponse = await fetch(joinRoomUrl, fetchOptions)
		const joinRoomResult = await joinRoomResponse.json()
		const nextcloudSessionId = joinRoomResult.ocs.data.sessionId

        this.send({
			'type': 'room',
			'room': {
				'roomid': token,
				'sessionid': nextcloudSessionId,
			},
		})
	}
}

/**
 * Base class for publishers and subscribers.
 *
 * After a Peer is created it must be explicitly connected to the HPB by calling
 * "connect()". This method returns a promise that is fulfilled once the peer
 * has connected, or rejected if the peer has not connected yet after some time.
 * "connect()" must be called once the signaling is already connected; this can
 * be done by waiting for "signaling.getSessionId()".
 *
 * Subclasses must set the "sessionId" attribute.
 */
class Peer {
	constructor(user, signalingSettings, signaling) {
		this.signaling = signaling

		let iceServers = signalingSettings.stunservers
		iceServers = iceServers.concat(signalingSettings.turnservers)

		this.peerConnection = new RTCPeerConnection({ iceServers: iceServers })
		this.peerConnection.onicecandidate = async event => {
			const candidate = event.candidate

			if (candidate) {
				this.send('candidate', candidate)
			}
		}

		this.connectedPromiseResolve = undefined
		this.connectedPromiseReject = undefined
		this.connectedPromise = new Promise((resolve, reject) => {
			this.connectedPromiseResolve = resolve
			this.connectedPromiseReject = reject
		})
	}

	async connect() {
		this.peerConnection.addEventListener('iceconnectionstatechange', () => {
			if (this.peerConnection.iceConnectionState === 'connected' || this.peerConnection.iceConnectionState === 'completed') {
				this.connectedPromiseResolve()
				this.connected = true
			}
		})

		const connectionTimeout = 5

		setTimeout(() => {
			if (!this.connected) {
				this.connectedPromiseReject('Peer has not connected in ' + connectionTimeout + ' seconds')
			}
		}, connectionTimeout * 1000)

		return this.connectedPromise
	}

	send(type, data) {
		this.signaling.sendMessage({
			to: this.sessionId,
			roomType: 'video',
			type: type,
			payload: data
		})
	}
}

/**
 * Helper class for publishers.
 *
 * A single publisher can be used on each signaling session.
 *
 * A publisher is connected to the HPB by sending an offer and handling the
 * returned answer.
 */
class Publisher extends Peer {
	constructor(user, signalingSettings, signaling, stream) {
		super(user, signalingSettings, signaling)

		stream.getTracks().forEach(track => {
			this.peerConnection.addTrack(track, stream)
		})

		this.signaling.addEventListener('message', event => {
			const message = event.detail

			if (message.data.type === 'answer') {
				const answer = message.data.payload
				this.peerConnection.setRemoteDescription(answer)
			}
		})
	}

	async connect() {
		this.sessionId = await this.signaling.getSessionId()

		const offer = await this.peerConnection.createOffer({ offerToReceiveAudio: 0, offerToReceiveVideo: 0 })
		await this.peerConnection.setLocalDescription(offer)

		this.send('offer', offer)

		return super.connect()
	}
}

async function newPublisher(signalingSettings, signaling, stream) {
	const publisher = new Publisher(user, signalingSettings, signaling, stream)
	const sessionId = await publisher.signaling.getSessionId()

	return [sessionId, publisher]
}

/**
 * Helper class for subscribers.
 *
 * Several subscribers can be used on a single signaling session provided that
 * each subscriber subscribes to a different publisher.
 *
 * A subscriber is connected to the HPB by requesting an offer, handling the
 * returned offer and sending back an answer.
 */
class Subscriber extends Peer {
	constructor(user, signalingSettings, signaling, publisherSessionId) {
		super(user, signalingSettings, signaling)

		this.sessionId = publisherSessionId

		this.signaling.addEventListener('message', async event => {
			const message = event.detail

			if (message.data.type === 'offer' && message.data.from === this.sessionId) {
				const offer = message.data.payload
				await this.peerConnection.setRemoteDescription(offer)

				const answer = await this.peerConnection.createAnswer()
				await this.peerConnection.setLocalDescription(answer)

				this.send('answer', answer)
			}
		})
	}

	async connect() {
		this.signaling.sendRequestOffer(this.sessionId)

		return super.connect()
	}
}

const connectionWarningTimeout = 5000

stream = await navigator.mediaDevices.getUserMedia({
	audio: true,
	video: false,
})

publishersCount = 5
publishers = []

function listenToPublisherConnectionChanges() {
	Object.values(publishers).forEach(publisher => {
		publisher.peerConnection.addEventListener('iceconnectionstatechange', event => {
			if (publisher.peerConnection.iceConnectionState === 'connected'
					|| publisher.peerConnection.iceConnectionState === 'completed') {
				clearTimeout(publisher.connectionWarning)
				publisher.connectionWarning = null

				return
			}

			if (publisher.peerConnection.iceConnectionState === 'disconnected') {
				// Brief disconnections are normal and expected; they are only
				// relevant if the connection has not been restored after some
				// seconds.
				publisher.connectionWarning = setTimeout(() => {
					console.warn('Publisher disconnected', publisher.sessionId)
				}, connectionWarningTimeout)
			} else if (publisher.peerConnection.iceConnectionState === 'failed') {
				console.warn('Publisher connection failed', publisher.sessionId)
			}
		})
	})
}

async function initPublishers() {
	for (let i = 0; i < publishersCount; i++) {
		const signalingSettings = await getSignalingSettings(user, password, token)
		const signaling = new Signaling(user, signalingSettings)

		const [publisherSessionId, publisher] = await newPublisher(signalingSettings, signaling, stream)

		try {
			await publisher.connect()
		} catch (exception) {
			console.warn('Publisher ' + i + ' error: ' + exception)
		}

		publishers[publisherSessionId] = publisher
	}

	console.info('Publishers started the siege')

	listenToPublisherConnectionChanges()
}

await initPublishers()

subscribersPerPublisherCount = 40
subscribers = []

function listenToSubscriberConnectionChanges() {
	subscribers.forEach(subscriber => {
		subscriber.peerConnection.addEventListener('iceconnectionstatechange', event => {
			if (subscriber.peerConnection.iceConnectionState === 'connected'
					|| subscriber.peerConnection.iceConnectionState === 'completed') {
				clearTimeout(subscriber.connectionWarning)
				subscriber.connectionWarning = null

				return
			}

			if (subscriber.peerConnection.iceConnectionState === 'disconnected') {
				// Brief disconnections are normal and expected; they are only
				// relevant if the connection has not been restored after some
				// seconds.
				subscriber.connectionWarning = setTimeout(() => {
					console.warn('Subscriber disconnected', subscriber.sessionId)
				}, connectionWarningTimeout)
			} else if (subscriber.peerConnection.iceConnectionState === 'failed') {
				console.warn('Subscriber connection failed', subscriber.sessionId)
			}
		})
	})
}

async function initSubscribers() {
	for (let i = 0; i < subscribersPerPublisherCount; i++) {
		// The same signaling session can be shared between subscribers to
		// different publishers.
		const signalingSettings = await getSignalingSettings(user, password, token)
		const signaling = new Signaling(user, signalingSettings)

		await signaling.getSessionId()

		Object.keys(publishers).forEach(async publisherSessionId => {
			const subscriber = new Subscriber(user, signalingSettings, signaling, publisherSessionId)

			subscribers.push(subscriber)
		})
	}

	for (let i = 0; i < subscribers.length; i++) {
		try {
			await subscribers[i].connect()
		} catch (exception) {
			console.warn('Subscriber ' + i + ' error: ' + exception)
		}
	}

	console.info('Subscribers started the siege')

	listenToSubscriberConnectionChanges()
}

await initSubscribers()

const closeConnections = function() {
	subscribers.forEach(subscriber => {
		subscriber.peerConnection.close()
	})

	Object.values(publishers).forEach(publisher => {
		publisher.peerConnection.close()
	})

	stream.getTracks().forEach(track => {
		track.stop()
	})
}
