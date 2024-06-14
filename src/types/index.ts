/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { components, operations } from './openapi/openapi-full.ts'

// General
type ApiOptions<T> = { params: T }
type ApiResponse<T> = Promise<{ data: T }>
type ApiResponseHeaders<T extends { headers: object }> = {
	[K in keyof T['headers'] as Lowercase<string & K>]: T['headers'][K];
}

// Capabilities
export type Capabilities = {
	[key: string]: Record<string, unknown>,
	spreed: components['schemas']['Capabilities'],
}
export type getCapabilitiesResponse = ApiResponse<operations['room-get-capabilities']['responses'][200]['content']['application/json']>

// Notifications
type NotificationAction = {
	label: string,
	link: string,
	type: 'WEB' | 'POST' | 'DELETE' | string,
	primary: boolean,
}

type RichObjectParameter = components['schemas']['RichObjectParameter']
type RichObject<T extends keyof RichObjectParameter = 'id'|'name'|'type'> = Pick<RichObjectParameter, 'id'|'name'|'type'|T>
export type Notification<T = Record<string, RichObject & Record<string, unknown>>> = {
	notificationId: number,
	app: string,
	user: string,
	datetime: string,
	objectType: string,
	objectId: number,
	subject: string,
	message: string,
	link: string,
	subjectRich: string,
	subjectRichParameters: T,
	messageRich: string,
	messageRichParameters: T,
	icon: string,
	shouldNotify: true,
	actions: NotificationAction[],
}

// Conversations
export type Conversation = components['schemas']['Room']

export type JoinRoomFullResponse = {
	headers: ApiResponseHeaders<operations['room-join-room']['responses']['200']>,
	data: operations['room-join-room']['responses']['200']['content']['application/json']
}

// Participants
export type Participant = components['schemas']['Participant']

// Chats
export type Mention = RichObject<'server'|'call-type'|'icon-url'>
export type File = RichObject<'size'|'path'|'link'|'mimetype'|'preview-available'> & {
	'etag': string,
	'permissions': string,
	'width': string,
	'height': string,
}
export type ChatMessage = components['schemas']['ChatMessageWithParent']
export type receiveMessagesParams = ApiOptions<operations['chat-receive-messages']['parameters']['query']>['params']
export type receiveMessagesResponse = ApiResponse<operations['chat-receive-messages']['responses'][200]['content']['application/json']>
export type getMessageContextParams = ApiOptions<operations['chat-get-message-context']['parameters']['query']>['params']
export type getMessageContextResponse = ApiResponse<operations['chat-get-message-context']['responses'][200]['content']['application/json']>
export type postNewMessageParams = ApiOptions<operations['chat-send-message']['parameters']['query']>['params']
export type postNewMessageResponse = ApiResponse<operations['chat-send-message']['responses'][201]['content']['application/json']>
export type clearHistoryResponse = ApiResponse<operations['chat-clear-history']['responses'][200]['content']['application/json']>
export type deleteMessageResponse = ApiResponse<operations['chat-delete-message']['responses'][200]['content']['application/json']>
export type editMessageParams = ApiOptions<operations['chat-edit-message']['parameters']['query']>['params']
export type editMessageResponse = ApiResponse<operations['chat-edit-message']['responses'][200]['content']['application/json']>
export type postRichObjectParams = ApiOptions<operations['chat-share-object-to-chat']['parameters']['query']>['params']
export type postRichObjectResponse = ApiResponse<operations['chat-share-object-to-chat']['responses'][201]['content']['application/json']>
export type setReadMarkerParams = ApiOptions<operations['chat-set-read-marker']['parameters']['query']>['params']
export type setReadMarkerResponse = ApiResponse<operations['chat-set-read-marker']['responses'][200]['content']['application/json']>
export type markUnreadResponse = ApiResponse<operations['chat-mark-unread']['responses'][200]['content']['application/json']>

// Avatars
export type setFileAvatarResponse = ApiResponse<operations['avatar-upload-avatar']['responses'][200]['content']['application/json']>
export type setEmojiAvatarParams = ApiOptions<operations['avatar-emoji-avatar']['parameters']['query']>['params']
export type setEmojiAvatarResponse = ApiResponse<operations['avatar-emoji-avatar']['responses'][200]['content']['application/json']>
export type deleteAvatarResponse = ApiResponse<operations['avatar-delete-avatar']['responses'][200]['content']['application/json']>

// Bots
export type Bot = components['schemas']['Bot']
export type BotWithDetails = components['schemas']['BotWithDetails']

export type getBotsResponse = ApiResponse<operations['bot-list-bots']['responses'][200]['content']['application/json']>
export type getBotsAdminResponse = ApiResponse<operations['bot-admin-list-bots']['responses'][200]['content']['application/json']>
export type enableBotResponse = ApiResponse<operations['bot-enable-bot']['responses'][201]['content']['application/json']>
export type disableBotResponse = ApiResponse<operations['bot-disable-bot']['responses'][200]['content']['application/json']>

// Federations
export type FederationInvite = components['schemas']['FederationInvite']
type FederationInviteRichParameters = {
	user1: RichObject<'server'>,
	roomName: RichObject,
	remoteServer: RichObject,
}
export type NotificationInvite = Notification<FederationInviteRichParameters>

export type getSharesResponse = ApiResponse<operations['federation-get-shares']['responses'][200]['content']['application/json']>
export type acceptShareResponse = ApiResponse<operations['federation-accept-share']['responses'][200]['content']['application/json']>
export type rejectShareResponse = ApiResponse<operations['federation-reject-share']['responses'][200]['content']['application/json']>

// Reactions
export type getReactionsParams = ApiOptions<operations['reaction-get-reactions']['parameters']['query']>['params']
export type getReactionsResponse = ApiResponse<operations['reaction-get-reactions']['responses'][200]['content']['application/json']>
export type addReactionParams = ApiOptions<operations['reaction-react']['parameters']['query']>['params']
export type addReactionResponse = ApiResponse<operations['reaction-react']['responses'][200]['content']['application/json']>
export type deleteReactionParams = ApiOptions<operations['reaction-delete']['parameters']['query']>['params']
export type deleteReactionResponse = ApiResponse<operations['reaction-delete']['responses'][200]['content']['application/json']>

// Breakout rooms
export type BreakoutRoom = components['schemas']['Room'] & {
	objectType: 'room',
}

export type configureBreakoutRoomsParams = ApiOptions<operations['breakout_room-configure-breakout-rooms']['parameters']['query']>['params']
export type configureBreakoutRoomsResponse = ApiResponse<operations['breakout_room-configure-breakout-rooms']['responses'][200]['content']['application/json']>
export type deleteBreakoutRoomsResponse = ApiResponse<operations['breakout_room-remove-breakout-rooms']['responses'][200]['content']['application/json']>
export type reorganizeAttendeesParams = ApiOptions<operations['breakout_room-apply-attendee-map']['parameters']['query']>['params']
export type reorganizeAttendeesResponse = ApiResponse<operations['breakout_room-apply-attendee-map']['responses'][200]['content']['application/json']>
export type getBreakoutRoomsResponse = ApiResponse<operations['room-get-breakout-rooms']['responses'][200]['content']['application/json']>
export type startBreakoutRoomsResponse = ApiResponse<operations['breakout_room-start-breakout-rooms']['responses'][200]['content']['application/json']>
export type stopBreakoutRoomsResponse = ApiResponse<operations['breakout_room-stop-breakout-rooms']['responses'][200]['content']['application/json']>
export type broadcastChatMessageParams = ApiOptions<operations['breakout_room-broadcast-chat-message']['parameters']['query']>['params']
export type broadcastChatMessageResponse = ApiResponse<operations['breakout_room-broadcast-chat-message']['responses'][201]['content']['application/json']>
export type getBreakoutRoomsParticipantsResponse = ApiResponse<operations['room-get-breakout-room-participants']['responses'][200]['content']['application/json']>
export type requestAssistanceResponse = ApiResponse<operations['breakout_room-request-assistance']['responses'][200]['content']['application/json']>
export type resetRequestAssistanceResponse = ApiResponse<operations['breakout_room-reset-request-for-assistance']['responses'][200]['content']['application/json']>
export type switchToBreakoutRoomParams = ApiOptions<operations['breakout_room-switch-breakout-room']['parameters']['query']>['params']
export type switchToBreakoutRoomResponse = ApiResponse<operations['breakout_room-switch-breakout-room']['responses'][200]['content']['application/json']>
