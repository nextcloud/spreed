/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type {
	components as componentsCore,
	operations as operationsCore,
} from './openapi/core/openapi_core.ts'
import type {
	components as componentsDav,
	operations as operationsDav,
} from './openapi/core/openapi_dav.ts'
import type {
	components as componentsFiles,
	operations as operationsFiles,
} from './openapi/core/openapi_files.ts'
import type {
	components as componentsShare,
	operations as operationsShare,
} from './openapi/core/openapi_files_sharing.ts'
import type {
	components as componentsProv,
	operations as operationsProv,
} from './openapi/core/openapi_provisioning_api.ts'

type ApiResponse<T> = Promise<{ data: T }>

// Groupware | DAV API
export type DavPrincipal = {
	calendarHomes: string[],
	calendarUserType: string,
	displayname: string,
	email: string,
	language: string,
	principalScheme: string,
	principalUrl: string,
	scheduleDefaultCalendarUrl: string,
	scheduleInbox: string,
	scheduleOutbox: string,
	url: string,
	userId: string,
	[key: string]: unknown,
}

export type DavCalendar = {
	displayname: string,
	color?: string,
	components: string[],
	allowedSharingModes: string[],
	currentUserPrivilegeSet: string[],
	enabled?: boolean,
	order: number,
	owner: string,
	resourcetype: string[],
	timezone?: string,
	transparency: string,
	url: string,
	[key: string]: unknown,
	isWriteable: () => boolean,
}

export type DavCalendarHome = {
	displayname: string,
	url: string,
	findAllCalendars: () => Promise<DavCalendar[]>,
}

export type OutOfOfficeResult = componentsDav['schemas']['CurrentOutOfOfficeData']
export type OutOfOfficeResponse = ApiResponse<operationsDav['out_of_office-get-current-out-of-office-data']['responses'][200]['content']['application/json']>

// FIXME upstream: the `recurrenceId` and `calendarAppUrl` fields are not in the OpenAPI spec
export type UpcomingEvent = componentsDav['schemas']['UpcomingEvent'] & {
	recurrenceId?: number | null,
	calendarAppUrl?: string | null,
}
export type UpcomingEventsResponse = ApiResponse<operationsDav['upcoming_events-get-events']['responses'][200]['content']['application/json']>

// Provisioning API
export type UserPreferencesParams = Required<operationsProv['preferences-set-preference']>['requestBody']['content']['application/json']
export type UserPreferencesResponse = ApiResponse<operationsProv['preferences-set-preference']['responses'][200]['content']['application/json']>

// Task Processing API
export type TaskProcessingResponse = ApiResponse<operationsCore['task_processing_api-get-task']['responses'][200]['content']['application/json']>

// Profile API
export type UserProfileData =  componentsCore['schemas']['ProfileData']
export type UserProfileResponse = ApiResponse<operationsCore['profile_api-get-profile-fields']['responses'][200]['content']['application/json']>

// Autocomplete API
export type AutocompleteResult =  componentsCore['schemas']['AutocompleteResult']
export type AutocompleteParams =  operationsCore['auto_complete-get']['parameters']['query']
export type AutocompleteResponse = ApiResponse<operationsCore['auto_complete-get']['responses'][200]['content']['application/json']>

// Unified Search API
type MessageSearchResultAttributes = {
	conversation: string,
	messageId: string,
	actorType: string,
	actorId: string,
	timestamp: string,
}
export type SearchMessagePayload = operationsCore['unified_search-search']['parameters']['query'] & {
	person?: string,
	since?: string | null,
	until?: string | null,
}

// FIXME upstream: the `attributes` field allows only string[] from OpenAPI spec
export type UnifiedSearchResultEntry = componentsCore['schemas']['UnifiedSearchResultEntry'] & {
	attributes: MessageSearchResultAttributes,
}
export type UnifiedSearchResponse = ApiResponse<operationsCore['unified_search-search']['responses'][200]['content']['application/json'] & {
	ocs: {
		meta: componentsCore['schemas']['OCSMeta'],
		data: componentsCore['schemas']['UnifiedSearchResult'] & {
			entries: (componentsCore['schemas']['UnifiedSearchResultEntry'] & {
				attributes: MessageSearchResultAttributes
			})[],
		},
	}
}>

// Files API
export type getFileTemplatesListResponse = ApiResponse<operationsFiles['template-list']['responses'][200]['content']['application/json']>
export type createFileFromTemplateParams = Required<operationsFiles['template-create']>['requestBody']['content']['application/json']
export type createFileFromTemplateResponse = ApiResponse<operationsFiles['template-create']['responses'][200]['content']['application/json']>

// Files sharing API
export type createFileShareParams = Required<operationsShare['shareapi-create-share']>['requestBody']['content']['application/json'] & {
	referenceId?: string, // unique message identifier to track in the response
	talkMetaData?: string, // JSON-encoded object (see lib/Chat/SystemMessage/Listener.php)
}
export type createFileShareResponse = ApiResponse<operationsShare['shareapi-create-share']['responses'][200]['content']['application/json']>
