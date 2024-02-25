import type { components, paths } from './openapi/openapi-full.ts'

// General
type ApiResponse<T> = Promise<{ data: T }>

// Conversations
export type Conversation = components['schemas']['Room']

// Bots
export type Bot = components['schemas']['Bot']
export type BotWithDetails = components['schemas']['BotWithDetails']

export type getBotsResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/{token}']['get']['responses'][200]['content']['application/json']>
export type getBotsAdminResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/admin']['get']['responses'][200]['content']['application/json']>
export type enableBotResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/{token}/{botId}']['post']['responses'][201]['content']['application/json']>
export type disableBotResponse = ApiResponse<paths['/ocs/v2.php/apps/spreed/api/{apiVersion}/bot/{token}/{botId}']['delete']['responses'][200]['content']['application/json']>
