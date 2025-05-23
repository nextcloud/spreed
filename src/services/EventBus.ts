/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import mitt from 'mitt'
import type { Emitter, EventType, Handler, WildcardHandler } from 'mitt'
import type { Route } from 'vue-router'

import type {
	ChatMessage,
	Conversation,
	InternalSignalingSession,
	SignalingSettings,
	StandaloneSignalingJoinSession,
	StandaloneSignalingUpdateSession,
} from '../types/index.ts'
import type { components } from '../types/openapi/openapi-full.ts'

// List of used events across the app
export type Events = {
	[key: EventType]: unknown
	'audio-player-ended': number
	'conversations-received': { singleConversation: boolean }
	'session-conflict-confirmation': string
	'deleted-session-detected': void
	'duplicate-session-detected': void
	'editing-message': void
	'editing-message-processing': { messageId: number, value: boolean }
	'focus-chat-input': void
	'focus-message': number // TODO: listener method can receive ...[messageId, smooth, highlightAnimation]
	'forbidden-route': { error: string }
	'joined-conversation': { token: string }
	'message-height-changed': { heightDiff: number }
	'poll-drafts-open': { selector?: string }
	'poll-editor-open': { id: number | null, fromDrafts: boolean, action?: string, selector?: string }
	'refresh-peer-list': void
	'retry-message': number
	'route-change': { from: Route, to: Route }
	'scroll-chat-to-bottom': { smooth?: boolean, force?: boolean }
	'should-refresh-chat-messages': void
	'should-refresh-conversations': { token: string, properties: Partial<Conversation> } | { all: true } | void
	'signaling-join-call': [string, number]
	'signaling-join-call-failed': [string, { meta: components['schemas']['OCSMeta'], data: { error: string } }]
	'signaling-join-room': [string]
	'signaling-participant-list-changed': void
	'signaling-participant-list-updated': void
	'signaling-recording-status-changed': [string, number]
	'signaling-settings-updated': [SignalingSettings]
	'signaling-users-changed': [StandaloneSignalingUpdateSession[]]
	'signaling-users-in-room': [InternalSignalingSession[]]
	'signaling-users-joined': [StandaloneSignalingJoinSession[]]
	'signaling-users-left': [string[]]
	'signaling-all-users-changed-in-call-to-disconnected': void
	'smart-picker-open': void
	'switch-to-conversation': { token: string }
	'talk:poll-added': { token: string, message: ChatMessage }
	'upload-discard': void
	'upload-finished': void
	'upload-start': void
	'new-conversation-dialog:show': void
	'open-conversations-list:show': void
	'call-phone-dialog:show': void
}

// Extended types for mitt() library
type GenericEventHandler = Handler<Events[keyof Events]> | WildcardHandler<Events>
type ExtendedEmitter = Emitter<Events> & {
	once: {
		<Key extends keyof Events>(type: Key, handler: Handler<Events[Key]>): void
		(type: '*', handler: WildcardHandler<Events>): void
	}
	_onceHandlers: Map<keyof Events | '*', Map<GenericEventHandler, GenericEventHandler>>
}

export const EventBus: ExtendedEmitter = mitt() as ExtendedEmitter

EventBus._onceHandlers = new Map()

/**
 * Register a one-time event handler for the given type
 *
 * @param type - type of event to listen for, or `'*'` for all events
 * @param handler - handler to call in response to given event
 */
EventBus.once = function<Key extends keyof Events>(type: Key, handler: GenericEventHandler) {
	/**
	 * @param args - event arguments: (type, event) or (event)
	 */
	const fn = (...args: Parameters<GenericEventHandler>) => {
		// @ts-expect-error: Vue: A spread argument must either have a tuple type or be passed to a rest parameter.
		handler(...args)
		// @ts-expect-error: Vue: No overload matches this call.
		this.off(type, handler)
	}
	this.on(type, fn)

	// Store reference to the original handler to be able to remove it later
	if (!EventBus._onceHandlers.has(type)) {
		EventBus._onceHandlers.set(type, new Map())
	}
	EventBus._onceHandlers.get(type)!.set(handler, fn)
}

const off = EventBus.off.bind(EventBus)
/**
 * OVERRIDING OF ORIGINAL MITT FUNCTION
 * Remove an event handler for the given type.
 * If `handler` is omitted, all handlers of the given type are removed.
 * @param type Type of event to unregister `handler` from (`'*'` to remove a wildcard handler)
 * @param [handler] Handler function to remove
 */
EventBus.off = function<Key extends keyof Events>(type: Key, handler?: GenericEventHandler) {
	// @ts-expect-error: Vue: No overload matches this call
	off(type, handler)

	if (!handler) {
		EventBus._onceHandlers.delete(type)
		return
	}

	const typeOnceHandlers = EventBus._onceHandlers.get(type)
	const onceHandler = typeOnceHandlers?.get(handler)
	if (onceHandler) {
		typeOnceHandlers!.delete(handler)
		if (!typeOnceHandlers!.size) {
			EventBus._onceHandlers.delete(type)
		}
		// @ts-expect-error: Vue: No overload matches this call
		off(type, onceHandler)
	}
}
