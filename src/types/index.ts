import type { components, operations } from './openapi/openapi-full.ts'

// General
type ApiOptions<T> = { params: T }
type ApiResponse<T> = Promise<{ data: T }>

// Conversations
export type Conversation = components['schemas']['Room']

// Chats
type ParamObject = {
	id: string,
	name: string,
	type: string,
}
export type Mention = ParamObject & {
	server?: string,
	'call-type'?: string,
	'icon-url'?: string,
}
type File = ParamObject & {
	'size': number,
	'path': string,
	'link': string,
	'etag': string,
	'permissions': number,
	'mimetype': string,
	'preview-available': string,
	'width': number,
	'height': number,
}
type MessageParameters = Record<string, ParamObject | Mention | File>
export type ChatMessage = Omit<components['schemas']['ChatMessage'], 'messageParameters'> & {
	messageParameters: MessageParameters
}

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
