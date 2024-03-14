import type { components, operations } from './openapi/openapi-full.ts'

// General
type ApiOptions<T> = { params: T }
type ApiResponse<T> = Promise<{ data: T }>

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

// Chats
export type Mention = RichObject<'server'|'call-type'|'icon-url'>
export type File = RichObject<'size'|'path'|'link'|'mimetype'|'preview-available'> & {
	'etag': string,
	'permissions': number,
	'width': number,
	'height': number,
}
export type ChatMessage = components['schemas']['ChatMessageWithParent']
export type receiveMessagesParams = ApiOptions<operations['chat-receive-messages']['parameters']['query']>['params']
export type receiveMessagesResponse = ApiResponse<operations['chat-receive-messages']['responses'][200]['content']['application/json']>
export type getMessageContextParams = ApiOptions<operations['chat-get-message-context']['parameters']['query']>['params']
export type getMessageContextResponse = ApiResponse<operations['chat-get-message-context']['responses'][200]['content']['application/json']>
export type postNewMessageParams = ApiOptions<operations['chat-send-message']['parameters']['query']>['params']
export type postNewMessageResponse = ApiResponse<operations['chat-send-message']['responses'][201]['content']['application/json']>
export type deleteMessageResponse = ApiResponse<operations['chat-delete-message']['responses'][200]['content']['application/json']>
export type editMessageParams = ApiOptions<operations['chat-edit-message']['parameters']['query']>['params']
export type editMessageResponse = ApiResponse<operations['chat-edit-message']['responses'][200]['content']['application/json']>
export type postRichObjectParams = ApiOptions<operations['chat-share-object-to-chat']['parameters']['query']>['params']
export type postRichObjectResponse = ApiResponse<operations['chat-share-object-to-chat']['responses'][201]['content']['application/json']>
export type setReadMarkerParams = ApiOptions<operations['chat-set-read-marker']['parameters']['query']>['params']
export type setReadMarkerResponse = ApiResponse<operations['chat-set-read-marker']['responses'][200]['content']['application/json']>

// Avatars
export type setFileAvatarResponse = ApiResponse<operations['avatar-upload-avatar']['responses'][200]['content']['application/json']>
export type setEmojiAvatarParams = ApiOptions<operations['avatar-emoji-avatar']['parameters']['query']>['params']
export type setEmojiAvatarResponse = ApiResponse<operations['avatar-emoji-avatar']['responses'][200]['content']['application/json']>
export type deleteAvatarResponse = ApiResponse<operations['avatar-delete-avatar']['responses'][200]['content']['application/json']>

// Bots
export type Bot = components['schemas']['Bot']
export type BotWithDetails = components['schemas']['BotWithDetails']

export type getBotsResponse = ApiResponse<operations['bot-list-bots']['responses'][200]['content']['application/json']>
export type getBotsAdminResponse = ApiResponse<operations['settings-admin-list-bots']['responses'][200]['content']['application/json']>
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
