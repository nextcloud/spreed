/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Talkbuchet is the helper tool for load/stress testing of Nextcloud Talk.
 *
 * Talkbuchet is a JavaScript script (Talkbuchet.js), and it is run using a web
 * browser. A Python script (Talkbuchet-cli.py) is provided to launch a web
 * browser, load Talkbuchet and control it from a command line interface (which
 * requires Selenium and certain Python packages to be available in the system).
 * A Bash script (Talkbuchet-run.sh) is provided to set up a Docker container
 * with Selenium, a web browser and all the needed Python dependencies for
 * Talkbuchet-cli.py.
 *
 * Please refer to the documentation in Talkbuchet-cli.py and Talkbuchet-run.sh
 * for information on how to control Talkbuchet and easily run it.
 *
 * A High Performance Backend (HPB) server must be configured in Nextcloud Talk
 * to use Talkbuchet.
 *
 * HOW TO SETUP (without using the CLI):
 * -----------------------------------------------------------------------------
 * - In the browser, log in the Nextcloud server (with the same user as in this
 *   script).
 * - Copy and paste the full script in the console of the browser.
 *
 *
 *
 * -----------------------------------------------------------------------------
 * SIEGE MODE
 * -----------------------------------------------------------------------------
 *
 * Siege mode is used for load testing of the Janus gateway running in the HPB
 * server. In siege mode Talkbuchet creates M publishers and N subscribers for
 * each publisher for a total of M+M*N connections with the Janus gateway.
 * Therefore, it can be used to verify that the server will be able to withstand
 * certain number of media connections (audio, video or both audio and video).
 *
 * In a real call every participant will subscribe to the publishers of the
 * other participants. That is, for X participants, Y of them publishers, there
 * will be (X-1)*Y subscribers. However, note that some participants may not be
 * publishers, for example, if the do not have publishing permissions, or if
 * they never had a microphone nor a camera selected (although they will be
 * publishers if the microphone and camera are selected but disabled, or if the
 * microphone or camera is no longer selected but was selected at some point
 * during the call).
 *
 * For example, in a normal call between 10 participants there will be 10
 * publishers and (10-1)*10=90 subscribers for a total of 100 connections.
 * However, if only two participants have publishing permissions then there will
 * be 2 publishers and (10-1)*2=18 subscribers for a total of 20 connections.
 *
 * To use the siege mode the signaling server of the HPB must be configured to
 * allow subscribing any stream:
 * https://github.com/strukturag/nextcloud-spreed-signaling/blob/a663dd43f90b0876630250012bb716136920fcd3/server.conf.in#L32-L35
 *
 * HOW TO RUN:
 * -----------------------------------------------------------------------------
 * - Set the user and appToken (a user must be used; guests do not work;
 *   generate an apptoken at index.php/settings/user/security) by calling
 *   "setCredentials(user, appToken)" in the console.
 * - If HPB clustering is enabled, set the token of a conversation (otherwise
 *   leave empty) by calling "setToken(token)" in the console.
 * - If media other than just audio should be used, start it by calling
 *   "startMedia(audio, video)" in the console.
 * - Set the desired numbers of publishers and subscribers per publisher (in a
 *   regular call with N participants you would have N publishers and N-1
 *   subscribers) by calling "setPublishersAndSubscribersCount(publishersCount,
 *   subscribersPerPublisherCount)" in the console.
 * - Once all the needed parameters are set execute "siege()" in the console.
 * - To run it again execute "siege()" again in the console; if any parameter
 *   needs to be changed it is recommended to first stop the previous siege by
 *   calling "closeConnections()" in the console before changing the parameters.
 *
 * HOW TO ENABLE AND DISABLE THE MEDIA DURING A TEST:
 * -----------------------------------------------------------------------------
 * You can manually enable and disable the media during a test by running the
 * following commands in the browser console:
 * - For audio:
 * setAudioEnabled(TRUE_OR_FALSE)
 * - For video:
 * setVideoEnabled(TRUE_OR_FALSE)
 *
 * Note that you can only enable and disable the original media specified in the
 * "getUserMedia" call.
 *
 * Additionally, you can also enable and disable the sent media streams during
 * a test by running the following commands in the browser console:
 * - For audio:
 * setSentAudioStreamEnabled(TRUE_OR_FALSE)
 * - For video:
 * setSentVideoStreamEnabled(TRUE_OR_FALSE)
 *
 * Currently Firefox behaviour is the same whether the media is disabled or the
 * sent media stream is disabled, so this makes no difference. Chromium, on the
 * other hand, sends some media data when the media is disabled, but stops it
 * when the sent media stream is disabled. In any case, please note that some
 * data will be always sent as long as there is a connection open, even if no
 * media is being sent.
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
 * connection state by running the following commands in the browser console:
 * - For the publishers:
 * checkPublishersConnections()
 * - For the subscribers:
 * checkSubscribersConnections()
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
 *
 *
 *
 * -----------------------------------------------------------------------------
 * VIRTUAL PARTICIPANT MODE
 * -----------------------------------------------------------------------------
 *
 * Virtual participant mode is used for load testing of clients. From the point
 * of view of the clients in a call the virtual participants are real
 * participants (they can be just listeners, but they can publish audio and/or
 * video), so several virtual participants can be added to a call to verify if
 * a client running on a specific system will be able to withstand certain
 * number of participants in a call.
 *
 * The reason to use virtual participants instead of real participants is that
 * virtual participants are either just in the call or publishing media, but
 * they will not subscribe to any other participant (which does not affect the
 * other clients, only the HPB server). Due to this they need much less
 * resources than real participants and therefore the system launching the test
 * would be able to add a much higher number of virtual participants than of
 * real participants.
 *
 * However, note that each web browser session can execute a single virtual
 * participant. Due to this it is recommended to use Talkbuchet CLI instead to
 * easily start several web browser sessions, each one with its own virtual
 * participant, and control the virtual participants from the CLI.
 *
 * HOW TO RUN:
 * -----------------------------------------------------------------------------
 * - Set the user and appToken (generate an apptoken at
 *   index.php/settings/user/security) by calling
 *   "setCredentials(user, appToken)" in the console. If credentials are not set
 *   a guest user will be used instead.
 * - Set the token of the conversation by calling "setToken(token)" in the
 *   console.
 * - Start the desired media by calling "startMedia(audio, video)" in the
 *   console. If not called (or both parameters are false) no media will be
 *   used.
 * - Once all the needed parameters are set execute "startVirtualParticipant()"
 *   in the console.
 * - To run it again execute "stopVirtualParticipant()" and then
 *   "startVirtualParticipant()" again in the console; if any parameter
 *   needs to be changed do it before starting the virtual participant again.
 *
 * TRIGGERING ACTIONS BY THE VIRTUAL PARTICIPANT:
 * -----------------------------------------------------------------------------
 * In general, for load testing of clients it is enough to start the virtual
 * participant and that is it. However, for development purposes it can be
 * useful to simulate certain actions by the virtual participant.
 *
 * Currently all the actions are simulated through data channel messages; the
 * equivalent signaling messages are not sent.
 *
 * The nick of the virtual participant can be set with:
 * sendNickThroughDataChannel(NICK)
 *
 * Note that sending the nick is not limited to guests; even if the virtual
 * participant is a registered user a nick can be sent. Moreover, clients may
 * not show any name at all for the virtual participant of a registered user
 * until a nick is sent, even if the user name is known by the client.
 *
 * If the virtual participant is a publisher the media can be enabled and
 * disabled as described in the siege mode. However, that does not notify other
 * participants in the call that the media state has changed. That can be done
 * instead with:
 * sendMediaEnabledStateThroughDataChannel(AUDIO_OR_VIDEO, TRUE_OR_FALSE)
 *
 * Similarly, when audio is enabled the message to notify other participants
 * when the virtual participant started or stopped speaking can be sent with:
 * sendSpeakingStateThroughDataChannel(TRUE_OR_FALSE)
 *
 * Note that the message is independent of the actual audio being sent; a
 * "speaking" event can be sent when audio is silent, and a "stoppedSpeaking"
 * event can be sent when audio is at full volume.
 *
 * DISCLAIMER:
 * -----------------------------------------------------------------------------
 * Like in siege mode, virtual participants mode does not take into account the
 * optimizations performed by Talk during calls, like reducing the video
 * quality based on the number of participants. This script is not meant to
 * accurately simulate Talk behaviour, but to provide a tool to perform rough
 * performance tests of specific call scenarios.
 *
 * Therefore, it should be kept in mind that in a real call with several video
 * participants the performance is very likely to be better than with several
 * virtual participants with video, as in that case the video quality will not
 * be adjusted based on the number of participants. Nevertheless, this script
 * could still be used to simulate a worst case scenario.
 */

// Sieges with guest users do not currently work, as they must join the
// conversation to not be kicked out from the signaling server. However, joining
// the conversation a second time causes the first guest to be unregistered.
// Regular users do not need to join the conversation, so the same user can be
// connected several times to the HPB.
let user = ''
let appToken = ''

// The conversation token is only strictly needed for guests or if HPB
// clustering is enabled.
let token = ''

// Number of streams to send
let publishersCount = 5
// Number of streams to receive
let subscribersPerPublisherCount = 40

const mediaConstraints = {
	audio: true,
	video: false,
}

let connectionWarningTimeout = 5000

/*
 * End of configuration section
 */

// To run the script the current page in the browser must be a page of the
// target Nextcloud instance, as cross-doman requests are not allowed, so the
// host is directly got from the current location.
const host = 'https://' + window.location.host

const capabitiliesUrl = host + '/ocs/v1.php/cloud/capabilities'

async function getCapabilities() {
	const fetchOptions = {
		headers: {
			'OCS-ApiRequest': true,
			'Accept': 'json',
		},
	}

	const capabilitiesResponse = await fetch(capabitiliesUrl, fetchOptions)
	const capabilities = await capabilitiesResponse.json()

	return capabilities.ocs.data
}

const capabilities = await getCapabilities()

function extractFeatureVersion(feature) {
	const talkFeatures = capabilities?.capabilities?.spreed?.features
	if (!talkFeatures) {
		console.error('Talk features not found', capabilities)
		throw new Error()
	}

	for (const talkFeature of talkFeatures) {
		if (talkFeature.startsWith(feature + '-v')) {
			return talkFeature.substring(feature.length + 2)
		}
	}

	console.error('Failed to get feature version for %s', feature, talkFeatures)
	throw new Error()
}

const signalingApiVersion = extractFeatureVersion('signaling')
const conversationApiVersion = extractFeatureVersion('conversation')

const talkOcsApiUrl = host + '/ocs/v2.php/apps/spreed/api/'
const signalingSettingsUrl = talkOcsApiUrl + 'v' + signalingApiVersion + '/signaling/settings'
const signalingBackendUrl = talkOcsApiUrl + 'v' + signalingApiVersion + '/signaling/backend'
let joinLeaveRoomUrl = talkOcsApiUrl + 'v' + conversationApiVersion + '/room/' + token + '/participants/active'
let joinLeaveCallUrl = talkOcsApiUrl + 'v' + conversationApiVersion + '/call/' + token

const publishers = []
const subscribers = []

let virtualParticipant

let stream

async function getSignalingSettings(user, appToken, token) {
	const fetchOptions = {
		headers: {
			'OCS-ApiRequest': true,
			'Accept': 'json',
		},
	}

	if (user) {
		fetchOptions.headers['Authorization'] = 'Basic ' + btoa(user + ':' + appToken)
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

		this.messageId = 1
		this.resolveMessageId = []

		const signalingUrl = this.sanitizeSignalingUrl(signalingSettings.server)

		this.socket = new WebSocket(signalingUrl)
		this.socket.onopen = () => {
			this.sendHello()
		}
		this.socket.onmessage = event => {
			const data = JSON.parse(event.data)

			if (data.id && this.resolveMessageId[data.id]) {
				this.resolveMessageId[data.id]()
				delete this.resolveMessageId[data.id]
			}

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
		})

		this.addEventListener('error', event => {
			const error = event.detail

			console.warn(error)

			if (error.code === 'not_allowed') {
				console.info('Is "allowsubscribeany = true" set in the signaling server configuration?')
			}
		})
	}

	sanitizeSignalingUrl(url) {
		if (url.startsWith('https://')) {
			url = 'wss://' + url.slice(8)
		} else if (url.startsWith('http://')) {
			url = 'ws://' + url.slice(7)
		}
		if (url.endsWith('/')) {
			url = url.slice(0, -1)
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

	async sendAndWaitForResponse(message) {
		return new Promise((resolve, reject) => {
			message.id = String(this.messageId++)
			this.resolveMessageId[message.id] = resolve

			this.send(message)
		})
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

		if (user) {
			fetchOptions.headers['Authorization'] = 'Basic ' + btoa(user + ':' + appToken)
		}

		const joinRoomResponse = await fetch(joinLeaveRoomUrl, fetchOptions)
		const joinRoomResult = await joinRoomResponse.json()
		const nextcloudSessionId = joinRoomResult.ocs.data.sessionId

        await this.sendAndWaitForResponse({
			'type': 'room',
			'room': {
				'roomid': token,
				'sessionid': nextcloudSessionId,
			},
		})
	}

	async joinCall(flags) {
		const fetchOptions = {
			headers: {
				'OCS-ApiRequest': true,
				'Accept': 'json',
			},
			method: 'POST',
			body: new URLSearchParams({
				flags,
			}),
		}

		if (user) {
			fetchOptions.headers['Authorization'] = 'Basic ' + btoa(user + ':' + appToken)
		}

		await fetch(joinLeaveCallUrl, fetchOptions)
	}

	async leaveCall() {
		const fetchOptions = {
			headers: {
				'OCS-ApiRequest': true,
				'Accept': 'json',
			},
			method: 'DELETE',
		}

		if (user) {
			fetchOptions.headers['Authorization'] = 'Basic ' + btoa(user + ':' + appToken)
		}

		await fetch(joinLeaveCallUrl, fetchOptions)
	}

	async leaveRoom() {
		const fetchOptions = {
			headers: {
				'OCS-ApiRequest': true,
				'Accept': 'json',
			},
			method: 'DELETE',
		}

		if (user) {
			fetchOptions.headers['Authorization'] = 'Basic ' + btoa(user + ':' + appToken)
		}

		await fetch(joinLeaveRoomUrl, fetchOptions)

        this.send({
			'type': 'room',
			'room': {
				'roomid': '',
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

		this.signaling.addEventListener('message', event => {
			const message = event.detail

			if (message.data.type === 'candidate' && message.data.from === this.sessionId) {
				const candidate = message.data.payload
				this.peerConnection.addIceCandidate(candidate.candidate)
			}
		})

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

		setTimeout(() => {
			if (!this.connected) {
				this.connectedPromiseReject('Peer has not connected in ' + connectionWarningTimeout + ' seconds')
			}
		}, connectionWarningTimeout)

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
		const signalingSettings = await getSignalingSettings(user, appToken, token)
		let signaling = null
		try {
			signaling = new Signaling(user, signalingSettings)
		} catch (exception) {
			console.error('Publisher ' + i + ' init error: ' + exception)
			continue
		}

		const [publisherSessionId, publisher] = await newPublisher(signalingSettings, signaling, stream)

		try {
			await publisher.connect()

			if ((i + 1) % 5 === 0 && (i + 1) < publishersCount) {
				console.info('Publisher started (' + (i + 1) + '/' + publishersCount + ')')
			}
		} catch (exception) {
			console.warn('Publisher ' + i + ' error: ' + exception)
		}

		publishers[publisherSessionId] = publisher
	}

	console.info('Publishers started the siege')

	listenToPublisherConnectionChanges()
}

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
		const signalingSettings = await getSignalingSettings(user, appToken, token)
		let signaling = null
		try {
			signaling = new Signaling(user, signalingSettings)
		} catch (exception) {
			console.error('Subscriber ' + i + ' init error: ' + exception)
			continue
		}

		await signaling.getSessionId()

		Object.keys(publishers).forEach(async publisherSessionId => {
			const subscriber = new Subscriber(user, signalingSettings, signaling, publisherSessionId)

			subscribers.push(subscriber)
		})
	}

	for (let i = 0; i < subscribers.length; i++) {
		try {
			await subscribers[i].connect()

			if ((i + 1) % 5 === 0 && (i + 1) < subscribers.length) {
				console.info('Subscriber started (' + (i + 1) + '/' + subscribers.length + ')')
			}
		} catch (exception) {
			console.warn('Subscriber ' + i + ' error: ' + exception)
		}
	}

	console.info('Subscribers started the siege')

	listenToSubscriberConnectionChanges()
}

// Expose publishers to CLI.
const getPublishers = function() {
	return publishers
}

// Expose subscribers to CLI.
const getSubscribers = function() {
	return subscribers
}

const closeConnections = function() {
	subscribers.forEach(subscriber => {
		subscriber.peerConnection.close()
	})
	subscribers.splice(0)

	Object.values(publishers).forEach(publisher => {
		publisher.peerConnection.close()
	})
	Object.keys(publishers).forEach(publisherSessionId => {
		delete publishers[publisherSessionId]
	})

	if (stream) {
		stream.getTracks().forEach(track => {
			track.stop()
		})
		stream = null
	}
}

const setAudioEnabled = function(enabled) {
	if (!stream || !stream.getAudioTracks().length) {
		console.error('Audio was not initialized')

		return
	}

	// There will be at most a single audio track.
	stream.getAudioTracks()[0].enabled = enabled
}

const setVideoEnabled = function(enabled) {
	if (!stream || !stream.getVideoTracks().length) {
		console.error('Video was not initialized')

		return
	}

	// There will be at most a single video track.
	stream.getVideoTracks()[0].enabled = enabled
}

const setSentAudioStreamEnabled = function(enabled) {
	if (!stream || !stream.getAudioTracks().length) {
		console.error('Audio was not initialized')

		return
	}

	Object.values(publishers).forEach(publisher => {
		// For simplicity it is assumed that if audio is enabled the audio
		// sender will always be the first one.
		const audioSender = publisher.peerConnection.getSenders()[0]
		if (enabled) {
			audioSender.replaceTrack(stream.getAudioTracks()[0])
		} else {
			audioSender.replaceTrack(null)
		}
	})
}

const setSentVideoStreamEnabled = function(enabled) {
	if (!stream || !stream.getVideoTracks().length) {
		console.error('Video was not initialized')

		return
	}

	Object.values(publishers).forEach(publisher => {
		// For simplicity it is assumed that if audio is not enabled the video
		// sender will always be the first one, otherwise the second one.
		let videoIndex = 0
		if (stream.getAudioTracks().length) {
			videoIndex = 1
		}

		const videoSender = publisher.peerConnection.getSenders()[videoIndex]
		if (enabled) {
			videoSender.replaceTrack(stream.getVideoTracks()[0])
		} else {
			videoSender.replaceTrack(null)
		}
	})
}

const checkPublishersConnections = function() {
	const iceConnectionStateCount = {}

	Object.keys(publishers).forEach(publisherSessionId => {
		publisher = publishers[publisherSessionId]

		console.info(publisherSessionId + ': ' + publisher.peerConnection.iceConnectionState)

		if (iceConnectionStateCount[publisher.peerConnection.iceConnectionState] === undefined) {
			iceConnectionStateCount[publisher.peerConnection.iceConnectionState] = 1
		} else {
			iceConnectionStateCount[publisher.peerConnection.iceConnectionState]++
		}
	})

	console.info('Summary:')
	console.info('  - New: ' + (iceConnectionStateCount['new'] ?? 0))
	console.info('  - Connected: ' + ((iceConnectionStateCount['connected'] ?? 0) + (iceConnectionStateCount['completed'] ?? 0)))
	console.info('  - Disconnected: ' + (iceConnectionStateCount['disconnected'] ?? 0))
	console.info('  - Failed: ' + (iceConnectionStateCount['failed'] ?? 0))
}

const checkSubscribersConnections = function() {
	const iceConnectionStateCount = {}

	i = 0

	subscribers.forEach(subscriber => {
		console.info(i + ': ' + subscriber.peerConnection.iceConnectionState)
		i++

		if (iceConnectionStateCount[subscriber.peerConnection.iceConnectionState] === undefined) {
			iceConnectionStateCount[subscriber.peerConnection.iceConnectionState] = 1
		} else {
			iceConnectionStateCount[subscriber.peerConnection.iceConnectionState]++
		}
	})

	console.info('Summary:')
	console.info('  - New: ' + (iceConnectionStateCount['new'] ?? 0))
	console.info('  - Connected: ' + ((iceConnectionStateCount['connected'] ?? 0) + (iceConnectionStateCount['completed'] ?? 0)))
	console.info('  - Disconnected: ' + (iceConnectionStateCount['disconnected'] ?? 0))
	console.info('  - Failed: ' + (iceConnectionStateCount['failed'] ?? 0))
}

const printPublisherStats = async function(publisherSessionId, stringify = false) {
	if (!(publisherSessionId in publishers)) {
		console.error('Invalid publisher session ID')

		return
	}

	stats = await publishers[publisherSessionId].peerConnection.getStats()

	for (stat of stats.values()) {
		if (stringify) {
			console.info(JSON.stringify(stat))
		} else {
			console.info(stat)
		}
	}
}

const printSubscriberStats = async function(index, stringify = false) {
	if (!(index in subscribers)) {
		console.error('Index out of range')

		return
	}

	stats = await subscribers[index].peerConnection.getStats()

	for (stat of stats.values()) {
		if (stringify) {
			console.info(JSON.stringify(stat))
		} else {
			console.info(stat)
		}
	}
}

const setCredentials = function(userToSet, appTokenToSet) {
	user = userToSet
	appToken = appTokenToSet
}

const setToken = function(tokenToSet) {
	token = tokenToSet

	joinLeaveRoomUrl = talkOcsApiUrl + 'v' + conversationApiVersion + '/room/' + token + '/participants/active'
	joinLeaveCallUrl = talkOcsApiUrl + 'v' + conversationApiVersion + '/call/' + token
}

const setPublishersAndSubscribersCount = function(publishersCountToSet, subscribersPerPublisherCountToSet) {
	publishersCount = publishersCountToSet
	subscribersPerPublisherCount = subscribersPerPublisherCountToSet
}

const startMedia = async function(audio, video) {
	if (stream) {
		stream.getTracks().forEach(track => {
			track.stop()
		})

		stream = null
	}

	if (audio !== undefined) {
		mediaConstraints.audio = audio
	}
	if (video !== undefined) {
		mediaConstraints.video = video
	}

	stream = await navigator.mediaDevices.getUserMedia(mediaConstraints)
}

const setConnectionWarningTimeout = function(connectionWarningTimeoutToSet) {
	connectionWarningTimeout = connectionWarningTimeoutToSet
}

const siege = async function() {
	if (!user || !appToken) {
		console.error('Credentials (user and appToken) are not set')

		return
	}

	closeConnections()

	if (!stream) {
		await startMedia()
	}

	console.info('Preparing to siege')

	await initPublishers()
	await initSubscribers()
}

// Expose virtual participant to CLI.
const getVirtualParticipant = function() {
	return virtualParticipant
}

const startVirtualParticipant = async function() {
	if (!token) {
		console.error('Conversation token is not set')

		return
	}

	const signalingSettings = await getSignalingSettings(user, appToken, token)
	let signaling = null
	try {
		signaling = new Signaling(user, signalingSettings)
	} catch (exception) {
		console.error('Virtual participant init error: ' + exception)
		return
	}

	let flags = 1

	let publisherSessionId
	let publisher

	if (stream) {
		[publisherSessionId, publisher] = await newPublisher(signalingSettings, signaling, stream)

		if (stream.getAudioTracks().length > 0) {
			flags |= 2
		}

		if (stream.getVideoTracks().length > 0) {
			flags |= 4
		}
	} else {
		await signaling.getSessionId()
	}

	await signaling.joinRoom()
	await signaling.joinCall(flags)

	virtualParticipant = {
		signaling
	}

	if (stream) {
		try {
			// Data channels are expected to be available for call participants.
			virtualParticipant.dataChannel = publisher.peerConnection.createDataChannel('status')

			await publisher.connect()

			publishers[publisherSessionId] = publisher
			virtualParticipant.publisherSessionId = publisherSessionId
		} catch (exception) {
			console.warn('Virtual participant publisher error: ' + exception)
		}
	}
}

const stopVirtualParticipant = async function() {
	if (!virtualParticipant) {
		return
	}

	if (virtualParticipant.publisherSessionId) {
		publishers[virtualParticipant.publisherSessionId].peerConnection.close()
		delete publishers[virtualParticipant.publisherSessionId]
	}

	await virtualParticipant.signaling.leaveCall()
	virtualParticipant.signaling.leaveRoom()

	virtualParticipant = null
}

function isVirtualParticipantAndDataChannelAvailable() {
	if (!virtualParticipant) {
		console.error('Virtual participant not started')

		return false
	}

	if (!virtualParticipant.dataChannel) {
		console.error('Data channel not open for virtual participant (was media enabled when virtual participant was started?)')

		return false
	}

	return true
}

const sendMediaEnabledStateThroughDataChannel = function(mediaType, enabled) {
	if (!isVirtualParticipantAndDataChannelAvailable()) {
		return
	}

	let messageType
	if (mediaType === 'audio' && enabled) {
		messageType = 'audioOn'
	} else if (mediaType === 'audio' && !enabled) {
		messageType = 'audioOff'
	} else if (mediaType === 'video' && enabled) {
		messageType = 'videoOn'
	} else if (mediaType === 'video' && !enabled) {
		messageType = 'videoOff'
	} else {
		console.error('Wrong parameters, expected "audio" or "video" and a boolean: ', mediaType, enabled)

		return
	}

	virtualParticipant.dataChannel.send(JSON.stringify({
		type: messageType
	}))
}

const sendSpeakingStateThroughDataChannel = function(speaking) {
	if (!isVirtualParticipantAndDataChannelAvailable()) {
		return
	}

	let messageType
	if (speaking) {
		messageType = 'speaking'
	} else {
		messageType = 'stoppedSpeaking'
	}

	virtualParticipant.dataChannel.send(JSON.stringify({
		type: messageType
	}))
}

const sendNickThroughDataChannel = function(nick) {
	if (!isVirtualParticipantAndDataChannelAvailable()) {
		return
	}

	if (!virtualParticipant.signaling.user) {
		payload = nick
	} else {
		payload = {
			name: nick,
			userid: virtualParticipant.signaling.user,
		}
	}

	virtualParticipant.dataChannel.send(JSON.stringify({
		type: 'nickChanged',
		payload,
	}))
}
