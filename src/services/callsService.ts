/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	callSIPDialOutResponse,
	CallSIPSendCallMessagePayload,
	fetchPeersResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { PARTICIPANT } from '../constants.ts'
import {
	signalingJoinCall,
	signalingLeaveCall,
	signalingSendCallMessage,
} from '../utils/webrtc/index.js'

/**
 * Join a call as participant
 *
 * The flags constrain the media to send when joining the call. If no flags are
 * provided both audio and video are available. Otherwise only the specified
 * media will be allowed to be sent.
 *
 * Note that the flags are constraints, but not requirements. Only the specified
 * media is allowed to be sent, but it is not guaranteed to be sent. For
 * example, if WITH_VIDEO is provided but the device does not have a camera.
 *
 * @param token The token of the call to be joined.
 * @param flags The available PARTICIPANT.CALL_FLAG for this participants
 * @param silent Whether the call should trigger a notifications and
 * sound for other participants or not
 * @param recordingConsent Whether the participant gave their consent to be recorded
 * @param silentFor List of participants that should not receive a notification about the call
 * @return The actual flags based on the available media
 */
const joinCall = async function(token: string, flags: number, silent: boolean, recordingConsent: boolean, silentFor: string[]): Promise<void> {
	return signalingJoinCall(token, flags, silent, recordingConsent, silentFor)
}

/**
 * Leave a call as participant
 *
 * @param token The token of the call to be left
 * @param all Whether to end the meeting for all
 */
const leaveCall = async function(token: string, all: boolean = false) {
	try {
		await signalingLeaveCall(token, all)
	} catch (error) {
		console.debug('Error while leaving call: ', error)
	}
}

const fetchPeers = async function(token: string, options: object): fetchPeersResponse {
	return await axios.get(generateOcsUrl('apps/spreed/api/v4/call/{token}', { token }), options)
}

/**
 * Call participant via SIP DialOut
 *
 * @param token The token of the conversation
 * @param attendeeId The attendee id to call to via SIP
 */
const callSIPDialOut = async function(token: string, attendeeId: number): callSIPDialOutResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/call/{token}/dialout/{attendeeId}', { token, attendeeId }))
}

/**
 * Hang up for phone participant
 *
 * @param sessionId Session id of receiver
 */
const callSIPHangupPhone = async function(sessionId: string) {
	await callSIPSendCallMessage(sessionId, { type: 'hangup' })
}

/**
 * Mute phone participant (prevent from speaking)
 *
 * @param sessionId Session id of receiver
 */
const callSIPMutePhone = async function(sessionId: string) {
	await callSIPSendCallMessage(sessionId, { type: 'mute', audio: PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_MICROPHONE })
}

/**
 * Unmute phone participant (allow to speaking and listening)
 *
 * @param sessionId Session id of receiver
 */
const callSIPUnmutePhone = async function(sessionId: string) {
	await callSIPSendCallMessage(sessionId, { type: 'mute', audio: PARTICIPANT.SIP_DIALOUT_FLAG.NONE })
}

/**
 * Hold a participant (prevent from listening)
 *
 * @param sessionId Session id of receiver
 */
const callSIPHoldPhone = async function(sessionId: string) {
	await callSIPSendCallMessage(sessionId, { type: 'mute', audio: PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_MICROPHONE | PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_SPEAKER })
}

/**
 * Send DTMF digits one per message (allowed characters: 0-9, *, #)
 *
 * @param sessionId Session id of receiver
 * @param digit DTMF digit to send
 */
const callSIPSendDTMF = async function(sessionId: string, digit: string) {
	await callSIPSendCallMessage(sessionId, { type: 'dtmf', digit })
}

/**
 * Send a message to SIP via signaling
 *
 * @param sessionId Session id of receiver
 * @param data Payload for message to be sent
 */
const callSIPSendCallMessage = async function(sessionId: string, data: CallSIPSendCallMessagePayload) {
	if (!sessionId) {
		console.debug('Session ID has not been provided')
		return
	}

	try {
		await signalingSendCallMessage({ type: 'control', payload: data, to: sessionId })
	} catch (error) {
		console.debug('Error while sending message: ', error)
	}
}

export {
	callSIPDialOut,
	callSIPHangupPhone,
	callSIPHoldPhone,
	callSIPMutePhone,
	callSIPSendDTMF,
	callSIPUnmutePhone,
	fetchPeers,
	joinCall,
	leaveCall,
}
