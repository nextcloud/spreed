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

// Avatars
export type setFileAvatarResponse = ApiResponse<operations['avatar-upload-avatar']['responses'][200]['content']['application/json']>
export type setEmojiAvatarParams = ApiOptions<operations['avatar-emoji-avatar']['parameters']['query']>
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
