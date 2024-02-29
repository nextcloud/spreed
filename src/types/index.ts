import type { components, paths } from './openapi/openapi-full.ts'

// General
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

// Bots
export type Bot = components['schemas']['Bot']
export type BotWithDetails = components['schemas']['BotWithDetails']

export type getBotsResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/{token}']['get']['responses'][200]['content']['application/json']>
export type getBotsAdminResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/admin']['get']['responses'][200]['content']['application/json']>
export type enableBotResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/{token}/{botId}']['post']['responses'][201]['content']['application/json']>
export type disableBotResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/{token}/{botId}']['delete']['responses'][200]['content']['application/json']>
