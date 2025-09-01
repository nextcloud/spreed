/**
 * SPDX-FileCopyrightText: 2020 Jitsi team at 8x8 and the community.
 * SPDX-License-Identifier: Apache-2.0
 *
 * Based on code from https://github.com/jitsi/jitsi-meet
 */

// Worker for E2EE/Insertable streams.

import { Context } from './JitsiEncryptionWorkerContext.js'

const contexts = new Map() // Map participant id => context

let sharedContext

/**
 * Retrieves the participant {@code Context}, creating it if necessary.
 *
 * @param {string} participantId - The participant whose context we need.
 * @return {object} The context.
 */
function getParticipantContext(participantId) {
	if (sharedContext) {
		return sharedContext
	}

	if (!contexts.has(participantId)) {
		contexts.set(participantId, new Context())
	}

	return contexts.get(participantId)
}

/**
 * Sets an encode / decode transform.
 *
 * @param {object} context - The participant context where the transform will be applied.
 * @param {string} operation - Encode / decode.
 * @param {object} readableStream - Readable stream part.
 * @param {object} writableStream - Writable stream part.
 */
function handleTransform(context, operation, readableStream, writableStream) {
	if (operation === 'encode' || operation === 'decode') {
		const transformFn = operation === 'encode' ? context.encodeFunction : context.decodeFunction
		const transformStream = new TransformStream({
			transform: transformFn.bind(context),
		})

		readableStream
			.pipeThrough(transformStream)
			.pipeTo(writableStream)
	} else {
		console.error(`Invalid operation: ${operation}`)
	}
}

onmessage = async (event) => {
	const { operation } = event.data

	if (operation === 'initialize') {
		const { sharedKey } = event.data

		if (sharedKey) {
			sharedContext = new Context({ sharedKey })
		}
	} else if (operation === 'encode' || operation === 'decode') {
		const { readableStream, writableStream, participantId } = event.data
		const context = getParticipantContext(participantId)

		handleTransform(context, operation, readableStream, writableStream)
	} else if (operation === 'setKey') {
		const { participantId, key, keyIndex } = event.data
		const context = getParticipantContext(participantId)

		if (key) {
			context.setKey(key, keyIndex)
		} else {
			context.setKey(false, keyIndex)
		}
	} else if (operation === 'cleanup') {
		const { participantId } = event.data

		contexts.delete(participantId)
	} else if (operation === 'cleanupAll') {
		contexts.clear()
	} else {
		console.error('e2ee worker', operation)
	}
}

// Operations using RTCRtpScriptTransform.
if (self.RTCTransformEvent) {
	self.onrtctransform = (event) => {
		const transformer = event.transformer
		const { operation, participantId } = transformer.options
		const context = getParticipantContext(participantId)

		handleTransform(context, operation, transformer.readable, transformer.writable)
	}
}
