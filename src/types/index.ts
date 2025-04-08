/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { AutocompleteResult } from './openapi/core/index.ts'
import type { components, operations } from './openapi/openapi-full.ts'

// General
type ApiResponse<T> = Promise<{ data: T }>
type ApiResponseHeaders<T extends { headers: object }> = {
	[K in keyof T['headers'] as Lowercase<string & K>]: T['headers'][K];
}
type ApiResponseUnwrapped<T> = Promise<{
	data: {
		ocs: {
			meta: components['schemas']['OCSMeta']
			data: T
		}
	}
}>

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
	objectId: string,
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
export type ConversationLastMessage = components['schemas']['RoomLastMessage']

export type JoinRoomFullResponse = {
	headers: ApiResponseHeaders<operations['room-join-room']['responses']['200']>,
	data: operations['room-join-room']['responses']['200']['content']['application/json']
}

// Participants
export type ParticipantStatus = {
	status?: string | null,
	message?: string | null,
	icon?: string | null,
	clearAt?: number | null,
}
export type Participant = components['schemas']['Participant']
export type ParticipantSearchResult = AutocompleteResult & {
	status: ParticipantStatus | '',
}

export type importEmailsParams = Required<operations['room-import-emails-as-participants']>['requestBody']['content']['application/json']
export type importEmailsResponse = ApiResponse<operations['room-import-emails-as-participants']['responses'][200]['content']['application/json']>

// Chats
export type Mention = RichObject<'server'|'call-type'|'icon-url'>
export type File = RichObject<'size'|'path'|'link'|'mimetype'|'preview-available'> & {
	'etag': string,
	'permissions': string,
	'width': string,
	'height': string,
}
export type ChatMessage = components['schemas']['ChatMessageWithParent']
export type receiveMessagesParams = operations['chat-receive-messages']['parameters']['query']
export type receiveMessagesResponse = ApiResponse<operations['chat-receive-messages']['responses'][200]['content']['application/json']>
export type getMessageContextParams = operations['chat-get-message-context']['parameters']['query']
export type getMessageContextResponse = ApiResponse<operations['chat-get-message-context']['responses'][200]['content']['application/json']>
export type postNewMessageParams = operations['chat-send-message']['requestBody']['content']['application/json']
export type postNewMessageResponse = ApiResponse<operations['chat-send-message']['responses'][201]['content']['application/json']>
export type clearHistoryResponse = ApiResponse<operations['chat-clear-history']['responses'][200]['content']['application/json']>
export type deleteMessageResponse = ApiResponse<operations['chat-delete-message']['responses'][200]['content']['application/json']>
export type editMessageParams = operations['chat-edit-message']['requestBody']['content']['application/json']
export type editMessageResponse = ApiResponse<operations['chat-edit-message']['responses'][200]['content']['application/json']>
export type postRichObjectParams = operations['chat-share-object-to-chat']['requestBody']['content']['application/json']
export type postRichObjectResponse = ApiResponse<operations['chat-share-object-to-chat']['responses'][201]['content']['application/json']>
export type setReadMarkerParams = Required<operations['chat-set-read-marker']>['requestBody']['content']['application/json']
export type setReadMarkerResponse = ApiResponse<operations['chat-set-read-marker']['responses'][200]['content']['application/json']>
export type markUnreadResponse = ApiResponse<operations['chat-mark-unread']['responses'][200]['content']['application/json']>
export type summarizeChatParams = operations['chat-summarize-chat']['requestBody']['content']['application/json']
export type summarizeChatResponse = ApiResponse<operations['chat-summarize-chat']['responses'][201]['content']['application/json']>
export type SummarizeChatTask = operations['chat-summarize-chat']['responses'][201]['content']['application/json']['ocs']['data']

// Avatars
export type setFileAvatarResponse = ApiResponse<operations['avatar-upload-avatar']['responses'][200]['content']['application/json']>
export type setEmojiAvatarParams = operations['avatar-emoji-avatar']['requestBody']['content']['application/json']
export type setEmojiAvatarResponse = ApiResponse<operations['avatar-emoji-avatar']['responses'][200]['content']['application/json']>
export type deleteAvatarResponse = ApiResponse<operations['avatar-delete-avatar']['responses'][200]['content']['application/json']>

// Bans
export type Ban = components['schemas']['Ban']

export type getBansResponse = ApiResponse<operations['ban-list-bans']['responses'][200]['content']['application/json']>
export type banActorParams = operations['ban-ban-actor']['requestBody']['content']['application/json']
export type banActorResponse = ApiResponse<operations['ban-ban-actor']['responses'][200]['content']['application/json']>
export type unbanActorResponse = ApiResponse<operations['ban-unban-actor']['responses'][200]['content']['application/json']>

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
export type getReactionsResponse = ApiResponse<operations['reaction-get-reactions']['responses'][200]['content']['application/json']>
export type addReactionParams = operations['reaction-react']['requestBody']['content']['application/json']
export type addReactionResponse = ApiResponse<operations['reaction-react']['responses'][200]['content']['application/json']>
export type deleteReactionParams = operations['reaction-delete']['parameters']['query']
export type deleteReactionResponse = ApiResponse<operations['reaction-delete']['responses'][200]['content']['application/json']>

// Breakout rooms
export type BreakoutRoom = components['schemas']['Room'] & {
	objectType: 'room',
}

export type configureBreakoutRoomsParams = operations['breakout_room-configure-breakout-rooms']['requestBody']['content']['application/json']
export type configureBreakoutRoomsResponse = ApiResponse<operations['breakout_room-configure-breakout-rooms']['responses'][200]['content']['application/json']>
export type deleteBreakoutRoomsResponse = ApiResponse<operations['breakout_room-remove-breakout-rooms']['responses'][200]['content']['application/json']>
export type reorganizeAttendeesParams = operations['breakout_room-apply-attendee-map']['requestBody']['content']['application/json']
export type reorganizeAttendeesResponse = ApiResponse<operations['breakout_room-apply-attendee-map']['responses'][200]['content']['application/json']>
export type getBreakoutRoomsResponse = ApiResponse<operations['room-get-breakout-rooms']['responses'][200]['content']['application/json']>
export type startBreakoutRoomsResponse = ApiResponse<operations['breakout_room-start-breakout-rooms']['responses'][200]['content']['application/json']>
export type stopBreakoutRoomsResponse = ApiResponse<operations['breakout_room-stop-breakout-rooms']['responses'][200]['content']['application/json']>
export type broadcastChatMessageParams = operations['breakout_room-broadcast-chat-message']['requestBody']['content']['application/json']
export type broadcastChatMessageResponse = ApiResponse<operations['breakout_room-broadcast-chat-message']['responses'][201]['content']['application/json']>
export type getBreakoutRoomsParticipantsResponse = ApiResponse<operations['room-get-breakout-room-participants']['responses'][200]['content']['application/json']>
export type requestAssistanceResponse = ApiResponse<operations['breakout_room-request-assistance']['responses'][200]['content']['application/json']>
export type resetRequestAssistanceResponse = ApiResponse<operations['breakout_room-reset-request-for-assistance']['responses'][200]['content']['application/json']>
export type switchToBreakoutRoomParams = operations['breakout_room-switch-breakout-room']['requestBody']['content']['application/json']
export type switchToBreakoutRoomResponse = ApiResponse<operations['breakout_room-switch-breakout-room']['responses'][200]['content']['application/json']>

// Polls
export type Poll = components['schemas']['Poll']
export type PollDraft = components['schemas']['PollDraft']

export type getPollResponse = ApiResponse<operations['poll-show-poll']['responses'][200]['content']['application/json']>
export type getPollDraftsResponse = ApiResponse<operations['poll-get-all-draft-polls']['responses'][200]['content']['application/json']>
export type createPollParams = operations['poll-create-poll']['requestBody']['content']['application/json']
export type createPollResponse = ApiResponse<operations['poll-create-poll']['responses'][201]['content']['application/json']>
export type createPollDraftResponse = ApiResponse<operations['poll-create-poll']['responses'][200]['content']['application/json']>
export type votePollParams = Required<operations['poll-vote-poll']>['requestBody']['content']['application/json']
export type votePollResponse = ApiResponse<operations['poll-vote-poll']['responses'][200]['content']['application/json']>
export type closePollResponse = ApiResponse<operations['poll-close-poll']['responses'][200]['content']['application/json']>
export type deletePollDraftResponse = ApiResponse<operations['poll-close-poll']['responses'][202]['content']['application/json']>

// Mentions
export type ChatMention = components['schemas']['ChatMentionSuggestion']
export type getMentionsParams = operations['chat-mentions']['parameters']['query']
export type getMentionsResponse = ApiResponse<operations['chat-mentions']['responses'][200]['content']['application/json']>

// AI Summary
export type {
	TaskProcessingResponse,
} from './openapi/core/index.ts'

// Out of office response
export type {
	OutOfOfficeResponse,
} from './openapi/core/index.ts'
