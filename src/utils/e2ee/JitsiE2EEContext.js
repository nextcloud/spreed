/**
 * SPDX-FileCopyrightText: 2020 Jitsi team at 8x8 and the community.
 * SPDX-License-Identifier: Apache-2.0
 *
 * Based on code from https://github.com/jitsi/jitsi-meet and updated to load
 * the worker from Nextcloud.
 */

/* global RTCRtpScriptTransform */

import Worker from './JitsiEncryptionWorker.worker.js'

// Flag to set on senders / receivers to avoid setting up the encryption transform
// more than once.
const kJitsiE2EE = Symbol('kJitsiE2EE');

/**
 * Context encapsulating the cryptography bits required for E2EE.
 * This uses the WebRTC Insertable Streams API which is explained in
 *   https://github.com/alvestrand/webrtc-media-streams/blob/master/explainer.md
 * that provides access to the encoded frames and allows them to be transformed.
 *
 * The encoded frame format is explained below in the _encodeFunction method.
 * High level design goals were:
 * - do not require changes to existing SFUs and retain (VP8) metadata.
 * - allow the SFU to rewrite SSRCs, timestamp, pictureId.
 * - allow for the key to be rotated frequently.
 */
export default class E2EEcontext {
	/**
	 * Build a new E2EE context instance, which will be used in a given conference.
	 * @param {boolean} [options.sharedKey] - whether there is a uniques key shared amoung all participants.
	 */
	constructor({ sharedKey } = {}) {
		this._worker = new Worker();

		this._worker.onerror = e => console.error(e);

		this._worker.postMessage({
			operation: 'initialize',
			sharedKey
		});
	}

	/**
	 * Cleans up all state associated with the given participant. This is needed when a
	 * participant leaves the current conference.
	 *
	 * @param {string} participantId - The participant that just left.
	 */
	cleanup(participantId) {
		this._worker.postMessage({
			operation: 'cleanup',
			participantId
		});
	}

	/**
	 * Cleans up all state associated with all participants in the conference. This is needed when disabling e2ee.
	 *
	 */
	cleanupAll() {
		this._worker.postMessage({
			operation: 'cleanupAll'
		});
	}

	/**
	 * Handles the given {@code RTCRtpReceiver} by creating a {@code TransformStream} which will inject
	 * a frame decoder.
	 *
	 * @param {RTCRtpReceiver} receiver - The receiver which will get the decoding function injected.
	 * @param {string} kind - The kind of track this receiver belongs to.
	 * @param {string} participantId - The participant id that this receiver belongs to.
	 */
	handleReceiver(receiver, kind, participantId) {
		if (receiver[kJitsiE2EE]) {
			return;
		}
		receiver[kJitsiE2EE] = true;

		if (window.RTCRtpScriptTransform) {
			const options = {
				operation: 'decode',
				participantId
			};

			receiver.transform = new RTCRtpScriptTransform(this._worker, options);
		} else {
			const receiverStreams = receiver.createEncodedStreams();

			this._worker.postMessage({
				operation: 'decode',
				readableStream: receiverStreams.readable,
				writableStream: receiverStreams.writable,
				participantId
			}, [ receiverStreams.readable, receiverStreams.writable ]);
		}
	}

	/**
	 * Handles the given {@code RTCRtpSender} by creating a {@code TransformStream} which will inject
	 * a frame encoder.
	 *
	 * @param {RTCRtpSender} sender - The sender which will get the encoding function injected.
	 * @param {string} kind - The kind of track this sender belongs to.
	 * @param {string} participantId - The participant id that this sender belongs to.
	 */
	handleSender(sender, kind, participantId) {
		if (sender[kJitsiE2EE]) {
			return;
		}
		sender[kJitsiE2EE] = true;

		if (window.RTCRtpScriptTransform) {
			const options = {
				operation: 'encode',
				participantId
			};

			sender.transform = new RTCRtpScriptTransform(this._worker, options);
		} else {
			const senderStreams = sender.createEncodedStreams();

			this._worker.postMessage({
				operation: 'encode',
				readableStream: senderStreams.readable,
				writableStream: senderStreams.writable,
				participantId
			}, [ senderStreams.readable, senderStreams.writable ]);
		}
	}

	/**
	 * Set the E2EE key for the specified participant.
	 *
	 * @param {string} participantId - the ID of the participant who's key we are setting.
	 * @param {Uint8Array | boolean} key - they key for the given participant.
	 * @param {Number} keyIndex - the key index.
	 */
	setKey(participantId, key, keyIndex) {
		this._worker.postMessage({
			operation: 'setKey',
			key,
			keyIndex,
			participantId
		});
	}
}
