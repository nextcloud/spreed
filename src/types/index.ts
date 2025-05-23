/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { AxiosError } from '@nextcloud/axios'

import type { AutocompleteResult } from './core.ts'
import type {
	components as componentsAdmin,
	operations as operationsAdmin,
} from './openapi/openapi-administration.ts'
import type {
	components as componentsFed,
	operations as operationsFed,
} from './openapi/openapi-federation.ts'
import type { components, operations } from './openapi/openapi.ts'

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

export type ApiErrorResponse<T = null> = AxiosError<{
	ocs: {
		meta: components['schemas']['OCSMeta']
		data: T
	}
}>

type SpreedCapabilities = components['schemas']['Capabilities']

// From https://github.com/nextcloud/password_policy/blob/master/lib/Capabilities.php
type PasswordPolicyCapabilities = {
	minLength: number,
	enforceNonCommonPassword: boolean,
	enforceNumericCharacters: boolean,
	enforceSpecialCharacters: boolean,
	enforceUpperLowerCase: boolean,
	api: {
		generate: string,
		validate: string,
	},
}

// Capabilities
export type Capabilities = {
	spreed: SpreedCapabilities,
	password_policy?: PasswordPolicyCapabilities,
}

export type getCapabilitiesResponse = ApiResponse<operations['room-get-capabilities']['responses'][200]['content']['application/json']>

// Initial state
export type InitialState = {
	spreed: {
		'has_cache_configured': boolean,
		'has_valid_subscription': boolean,
		'signaling_mode': string,
		'signaling_servers': {
			hideWarning: boolean,
			secret: string,
			servers: { server: string, verify: boolean }[],
		},
	},
}

// Notifications
type NotificationAction = {
	label: string,
	link: string,
	type: 'WEB' | 'POST' | 'DELETE' | string,
	primary: boolean,
}

type RichObjectParameter = components['schemas']['RichObjectParameter']
type RichObject<T extends keyof RichObjectParameter = 'id' | 'name' | 'type'> = Pick<RichObjectParameter, 'id' | 'name' | 'type' | T>
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

// Signaling
export type SignalingSettings = components['schemas']['SignalingSettings']

export type InternalSignalingSession = components['schemas']['SignalingSession']

// Based on https://github.com/strukturag/nextcloud-spreed-signaling/blob/master/api_signaling.go:
// EventServerMessage - room - Join
export type StandaloneSignalingJoinSession = {
	userid: string,
	user?:
		| { displayname: string }
		| { callid: string, number: string, type: string }, // Phone number
	sessionid: string, // Standalone signaling id
	roomsessionid?: string, // Nextcloud id
	features?: string[],
	federated?: boolean,
}

// EventServerMessage - room - Leave
export type StandaloneSignalingLeaveSession = string // Standalone signaling id

// EventServerMessage - participants - Update
export type StandaloneSignalingUpdateSession = {
	inCall: number,
	lastPing: number,
	sessionId: string, // Standalone signaling id
	nextcloudSessionId?: string, // Nextcloud id
	participantPermissions?: number,
	participantType?: number,
	userId?: string,
	// Since Talk v20, treat as optional
	actorId?: string,
	actorType?: string,
	displayName?: string,
	// Internal participant (Recording server, phone number)
	features?: string[],
	internal?: boolean,
	// Phone number only
	virtual?: boolean,
}

// Conversations
export type Conversation = components['schemas']['Room'] & {
	// internal parameter up to mock a conversation object
	isDummyConversation?: true
}

export type getAllConversationsParams = operations['room-get-rooms']['parameters']['query']
export type getAllConversationsResponse = ApiResponse<operations['room-get-rooms']['responses'][200]['content']['application/json']>
export type getSingleConversationResponse = ApiResponse<operations['room-get-single-room']['responses'][200]['content']['application/json']>
export type getNoteToSelfConversationResponse = ApiResponse<operations['room-get-note-to-self-conversation']['responses'][200]['content']['application/json']>
export type getListedConversationsParams = operations['room-get-listed-rooms']['parameters']['query']
export type getListedConversationsResponse = ApiResponse<operations['room-get-listed-rooms']['responses'][200]['content']['application/json']>

export type createConversationParams = Required<operations['room-create-room']>['requestBody']['content']['application/json']
export type createConversationResponse = ApiResponse<operations['room-create-room']['responses'][200]['content']['application/json']>
export type legacyCreateConversationParams = Pick<createConversationParams, 'roomType' | 'roomName' | 'password' | 'objectType' | 'objectId' | 'invite' | 'source'>
export type deleteConversationResponse = ApiResponse<operations['room-delete-room']['responses'][200]['content']['application/json']>
export type unbindConversationFromObjectResponse = ApiResponse<operations['room-unbind-room-from-object']['responses'][200]['content']['application/json']>

export type setConversationNameParams = Required<operations['room-rename-room']>['requestBody']['content']['application/json']
export type setConversationNameResponse = ApiResponse<operations['room-rename-room']['responses'][200]['content']['application/json']>
export type setConversationPasswordParams = Required<operations['room-set-password']>['requestBody']['content']['application/json']
export type setConversationPasswordResponse = ApiResponse<operations['room-set-password']['responses'][200]['content']['application/json']>
export type setConversationDescriptionParams = Required<operations['room-set-description']>['requestBody']['content']['application/json']
export type setConversationDescriptionResponse = ApiResponse<operations['room-set-description']['responses'][200]['content']['application/json']>
export type addConversationToFavoritesResponse = ApiResponse<operations['room-add-to-favorites']['responses'][200]['content']['application/json']>
export type removeConversationFromFavoritesResponse = ApiResponse<operations['room-remove-from-favorites']['responses'][200]['content']['application/json']>
export type archiveConversationResponse = ApiResponse<operations['room-archive-conversation']['responses'][200]['content']['application/json']>
export type unarchiveConversationResponse = ApiResponse<operations['room-unarchive-conversation']['responses'][200]['content']['application/json']>
export type setConversationNotifyLevelParams = Required<operations['room-set-notification-level']>['requestBody']['content']['application/json']
export type setConversationNotifyLevelResponse = ApiResponse<operations['room-set-notification-level']['responses'][200]['content']['application/json']>
export type setConversationNotifyCallsParams = Required<operations['room-set-notification-calls']>['requestBody']['content']['application/json']
export type setConversationNotifyCallsResponse = ApiResponse<operations['room-set-notification-calls']['responses'][200]['content']['application/json']>
export type makeConversationPublicParams = Required<operations['room-make-public']>['requestBody']['content']['application/json']
export type makeConversationPublicResponse = ApiResponse<operations['room-make-public']['responses'][200]['content']['application/json']>
export type makeConversationPrivateResponse = ApiResponse<operations['room-make-private']['responses'][200]['content']['application/json']>
export type setConversationSipParams = Required<operations['room-setsip-enabled']>['requestBody']['content']['application/json']
export type setConversationSipResponse = ApiResponse<operations['room-setsip-enabled']['responses'][200]['content']['application/json']>
export type setConversationLobbyParams = Required<operations['room-set-lobby']>['requestBody']['content']['application/json']
export type setConversationLobbyResponse = ApiResponse<operations['room-set-lobby']['responses'][200]['content']['application/json']>
export type setConversationRecordingParams = Required<operations['room-set-recording-consent']>['requestBody']['content']['application/json']
export type setConversationRecordingResponse = ApiResponse<operations['room-set-recording-consent']['responses'][200]['content']['application/json']>
export type setConversationReadonlyParams = Required<operations['room-set-read-only']>['requestBody']['content']['application/json']
export type setConversationReadonlyResponse = ApiResponse<operations['room-set-read-only']['responses'][200]['content']['application/json']>
export type setConversationListableParams = Required<operations['room-set-listable']>['requestBody']['content']['application/json']
export type setConversationListableResponse = ApiResponse<operations['room-set-listable']['responses'][200]['content']['application/json']>
export type setConversationMentionsPermissionsParams = Required<operations['room-set-mention-permissions']>['requestBody']['content']['application/json']
export type setConversationMentionsPermissionsResponse = ApiResponse<operations['room-set-mention-permissions']['responses'][200]['content']['application/json']>
export type setConversationPermissionsParams = Required<operations['room-set-permissions']>['requestBody']['content']['application/json']
export type setConversationPermissionsResponse = ApiResponse<operations['room-set-permissions']['responses'][200]['content']['application/json']>
export type setConversationMessageExpirationParams = Required<operations['room-set-message-expiration']>['requestBody']['content']['application/json']
export type setConversationMessageExpirationResponse = ApiResponse<operations['room-set-message-expiration']['responses'][200]['content']['application/json']>
export type markConversationAsImportantResponse = ApiResponse<operations['room-mark-conversation-as-important']['responses'][200]['content']['application/json']>
export type markConversationAsUnimportantResponse = ApiResponse<operations['room-mark-conversation-as-unimportant']['responses'][200]['content']['application/json']>
export type markConversationAsSensitiveResponse = ApiResponse<operations['room-mark-conversation-as-sensitive']['responses'][200]['content']['application/json']>
export type markConversationAsInsensitiveResponse = ApiResponse<operations['room-mark-conversation-as-insensitive']['responses'][200]['content']['application/json']>

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
export type Mention = RichObject<'server' | 'call-type' | 'icon-url'> & { 'mention-id'?: string }
export type File = RichObject<'size' | 'path' | 'link' | 'mimetype' | 'preview-available'> & {
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
export type upcomingRemindersResponse = ApiResponse<operations['chat-get-upcoming-reminders']['responses'][200]['content']['application/json']>
export type UpcomingReminder = components['schemas']['ChatReminderUpcoming']

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

// Talk Dashboard
export type DashboardEventRoom = components['schemas']['DashboardEvent']
export type getDashboardEventRoomsResponse = ApiResponse<operations['calendar_integration-get-dashboard-events']['responses'][200]['content']['application/json']>

// Bots
export type Bot = components['schemas']['Bot']
export type BotWithDetails = componentsAdmin['schemas']['BotWithDetails']

export type getBotsResponse = ApiResponse<operations['bot-list-bots']['responses'][200]['content']['application/json']>
export type getBotsAdminResponse = ApiResponse<operationsAdmin['bot-admin-list-bots']['responses'][200]['content']['application/json']>
export type enableBotResponse = ApiResponse<operations['bot-enable-bot']['responses'][201]['content']['application/json']>
export type disableBotResponse = ApiResponse<operations['bot-disable-bot']['responses'][200]['content']['application/json']>

// Certificate
export type certificateExpirationParams = operationsAdmin['certificate-get-certificate-expiration']['parameters']['query']
export type certificateExpirationResponse = ApiResponse<operationsAdmin['certificate-get-certificate-expiration']['responses'][200]['content']['application/json']>

// Federations
export type FederationInvite = componentsFed['schemas']['FederationInvite']
type FederationInviteRichParameters = {
	user1: RichObject<'server'>,
	roomName: RichObject,
	remoteServer: RichObject,
}
export type NotificationInvite = Notification<FederationInviteRichParameters>

export type getSharesResponse = ApiResponse<operationsFed['federation-get-shares']['responses'][200]['content']['application/json']>
export type acceptShareResponse = ApiResponse<operationsFed['federation-accept-share']['responses'][200]['content']['application/json']>
export type rejectShareResponse = ApiResponse<operationsFed['federation-reject-share']['responses'][200]['content']['application/json']>

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
export type updatePollDraftParams = operations['poll-update-draft-poll']['requestBody']['content']['application/json']
export type updatePollDraftResponse = ApiResponse<operations['poll-update-draft-poll']['responses'][200]['content']['application/json']>
export type createPollDraftResponse = ApiResponse<operations['poll-create-poll']['responses'][200]['content']['application/json']>
export type votePollParams = Required<operations['poll-vote-poll']>['requestBody']['content']['application/json']
export type votePollResponse = ApiResponse<operations['poll-vote-poll']['responses'][200]['content']['application/json']>
export type closePollResponse = ApiResponse<operations['poll-close-poll']['responses'][200]['content']['application/json']>
export type deletePollDraftResponse = ApiResponse<operations['poll-close-poll']['responses'][202]['content']['application/json']>

export type requiredPollParams = Omit<createPollParams, 'draft'>

// Mentions
export type ChatMention = components['schemas']['ChatMentionSuggestion']
export type getMentionsParams = operations['chat-mentions']['parameters']['query']
export type getMentionsResponse = ApiResponse<operations['chat-mentions']['responses'][200]['content']['application/json']>

// AI Summary
export type {
	TaskProcessingResponse,
} from './core.ts'

// Teams (circles)
export type TeamProbe = {
	id: string,
	name: string,
	displayName: string,
	sanitizedName: string,
	source: number,
	population: number,
	config: number,
	description: string,
	url: string,
	creation: number,
	initiator: null
}
export type getTeamsProbeResponse = ApiResponseUnwrapped<TeamProbe[]>

// Groupware | DAV API
export type {
	DavCalendar,
	DavCalendarHome,
	DavPrincipal,
	OutOfOfficeResult,
	OutOfOfficeResponse,
	UpcomingEvent,
	UpcomingEventsResponse,
} from './core.ts'

export type DashboardEvent = components['schemas']['DashboardEvent']

export type scheduleMeetingParams = Required<operations['room-schedule-meeting']>['requestBody']['content']['application/json']
export type scheduleMeetingResponse = ApiResponse<operations['room-schedule-meeting']['responses'][200]['content']['application/json']>
export type getMutualEventsResponse = ApiResponse<operations['calendar_integration-get-mutual-events']['responses'][200]['content']['application/json']>

export type EventTimeRange = {
	start: number | null
	end: number | null
}

// User profile / preferences response
export type {
	UserProfileData,
	UserProfileResponse,
	UserPreferencesParams,
	UserPreferencesResponse,
} from './core.ts'

// Settings
export type setSipSettingsParams = Required<operationsAdmin['settings-setsip-settings']>['requestBody']['content']['application/json']
export type setSipSettingsResponse = ApiResponse<operationsAdmin['settings-setsip-settings']['responses'][200]['content']['application/json']>
export type setUserSettingsParams = Required<operations['settings-set-user-setting']>['requestBody']['content']['application/json']
export type setUserSettingsResponse = ApiResponse<operations['settings-set-user-setting']['responses'][200]['content']['application/json']>

// Payload for NcSelect with `user-select`
export type UserFilterObject = {
	id: string,
	displayName: string,
	isNoUser: boolean,
	user: string,
	disableMenu: boolean,
	showUserStatus: boolean,
}

// Autocomplete API
export type {
	AutocompleteResult,
	AutocompleteParams,
	AutocompleteResponse,
} from './core.ts'

// Unified Search API
export type {
	SearchMessagePayload,
	UnifiedSearchResultEntry,
	UnifiedSearchResponse,
} from './core.ts'

// Files API
export type {
	getFileTemplatesListResponse,
	createFileFromTemplateParams,
	createFileFromTemplateResponse,
} from './core.ts'

// Files sharing API
export type {
	createFileShareParams,
	createFileShareResponse,
} from './core.ts'
