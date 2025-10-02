/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const CONFIG = {
	RECORDING_CONSENT: {
		OFF: 0,
		REQUIRED: 1,
		OPTIONAL: 2,
	},
	EXPERIMENTAL: {
		/**
		 * Since 21.1.0
		 * Instead of refreshing the participant list repeatingly,
		 * the data is generated from received signaling messages
		 */
		UPDATE_PARTICIPANTS: 1,
		/**
		 * Since 21.1.0
		 * Make automatic attempts to recover suspended / expired signaling session
		 * to allow join the call without page reload
		 */
		RECOVER_SESSION: 2,
	},
} as const

export const SIGNALING = {
	MODE: {
		INTERNAL: 'internal',
		EXTERNAL: 'external',
		CLUSTER_CONVERSATION: 'conversation_cluster',
	},
} as const

export const SESSION = {
	STATE: {
		INACTIVE: 0,
		ACTIVE: 1,
	},
} as const

export const CHAT = {
	FETCH_LIMIT: 100,
	MINIMUM_VISIBLE: 20,
	FETCH_OLD: 0,
	FETCH_NEW: 1,
} as const

export const CALL = {
	RECORDING: {
		OFF: 0,
		VIDEO: 1,
		AUDIO: 2,
		VIDEO_STARTING: 3,
		AUDIO_STARTING: 4,
		FAILED: 5,
	},
	RECORDING_CONSENT: {
		DISABLED: 0,
		ENABLED: 1,
	},
} as const

export const CONVERSATION = {
	START_CALL: {
		EVERYONE: 0,
		USERS: 1,
		MODERATORS: 2,
	},

	STATE: {
		READ_WRITE: 0,
		READ_ONLY: 1,
	},

	LISTABLE: {
		NONE: 0,
		USERS: 1,
		ALL: 2,
	},

	MENTION_PERMISSIONS: {
		EVERYONE: 0,
		MODERATORS: 1,
	},

	TYPE: {
		ONE_TO_ONE: 1,
		GROUP: 2,
		PUBLIC: 3,
		CHANGELOG: 4,
		ONE_TO_ONE_FORMER: 5,
		NOTE_TO_SELF: 6,
	},

	BREAKOUT_ROOM_MODE: {
		NOT_CONFIGURED: 0,
		AUTOMATIC: 1,
		MANUAL: 2,
		FREE: 3,
	},

	BREAKOUT_ROOM_STATUS: {
		// Main room
		STOPPED: 0,
		STARTED: 1,
		// Breakout rooms
		STATUS_ASSISTANCE_RESET: 0,
		STATUS_ASSISTANCE_REQUESTED: 2,
	},

	OBJECT_TYPE: {
		EMAIL: 'emails',
		EVENT: 'event',
		FILE: 'file',
		/** @deprecated */
		PHONE_LEGACY: 'phone',
		PHONE_PERSISTENT: 'phone_persist',
		PHONE_TEMPORARY: 'phone_temporary',
		CIRCLES: 'circles',
		VIDEO_VERIFICATION: 'share:password',
		BREAKOUT_ROOM: 'room',
		EXTENDED: 'extended_conversation',
		INSTANT_MEETING: 'instant_meeting',
		DEFAULT: '',
	},

	OBJECT_ID: {
		PHONE_INCOMING: 'direct-dialin',
		PHONE_OUTGOING: 'phone',
	},

	LIST_STYLE: {
		TWO_LINES: 'two-lines',
		COMPACT: 'compact',
	},

	MAX_NAME_LENGTH: 255,
} as const

export const ATTENDEE = {
	ACTOR_TYPE: {
		USERS: 'users',
		GUESTS: 'guests',
		EMAILS: 'emails',
		GROUPS: 'groups',
		CIRCLES: 'circles',
		TEAMS: 'teams',
		BOTS: 'bots',
		BRIDGED: 'bridged',
		FEDERATED_USERS: 'federated_users',
		PHONES: 'phones',
		DELETED_USERS: 'deleted_users',
		/* @internal Only use with server APIs (like /core/autocomplete/get) and never with Talk APIs */
		REMOTES: 'remotes',
	},

	BOT_PREFIX: 'bot-',
	BRIDGE_BOT_ID: 'bridge-bot',

	CHANGELOG_BOT_ID: 'changelog',
	SAMPLE_BOT_ID: 'sample',
} as const

export const MESSAGE = {
	CHAT_BEGIN_ID: -2,
	CHAT_MIGRATION_ID: -1,

	SYSTEM_TYPE: {
		AUDIO_RECORDING_STARTED: 'audio_recording_started',
		AUDIO_RECORDING_STOPPED: 'audio_recording_stopped',
		AVATAR_REMOVED: 'avatar_removed',
		AVATAR_SET: 'avatar_set',
		BREAKOUT_ROOMS_STARTED: 'breakout_rooms_started',
		BREAKOUT_ROOMS_STOPPED: 'breakout_rooms_stopped',
		CALL_ENDED: 'call_ended',
		CALL_ENDED_EVERYONE: 'call_ended_everyone',
		CALL_JOINED: 'call_joined',
		CALL_LEFT: 'call_left',
		CALL_MISSED: 'call_missed',
		CALL_STARTED: 'call_started',
		CIRCLE_ADDED: 'circle_added',
		CIRCLE_REMOVED: 'circle_removed',
		CONVERSATION_CREATED: 'conversation_created',
		CONVERSATION_RENAMED: 'conversation_renamed',
		DESCRIPTION_REMOVED: 'description_removed',
		DESCRIPTION_SET: 'description_set',
		FEDERATED_USER_ADDED: 'federated_user_added',
		FEDERATED_USER_REMOVED: 'federated_user_removed',
		FILE_SHARED: 'file_shared',
		GROUP_ADDED: 'group_added',
		GROUP_REMOVED: 'group_removed',
		GUEST_MODERATOR_DEMOTED: 'guest_moderator_demoted',
		GUEST_MODERATOR_PROMOTED: 'guest_moderator_promoted',
		GUESTS_ALLOWED: 'guests_allowed',
		GUESTS_DISALLOWED: 'guests_disallowed',
		HISTORY_CLEARED: 'history_cleared',
		LISTABLE_ALL: 'listable_all',
		LISTABLE_NONE: 'listable_none',
		LISTABLE_USERS: 'listable_users',
		LOBBY_NON_MODERATORS: 'lobby_non_moderators',
		LOBBY_NONE: 'lobby_none',
		LOBBY_TIMER_REACHED: 'lobby_timer_reached',
		MATTERBRIDGE_CONFIG_ADDED: 'matterbridge_config_added',
		MATTERBRIDGE_CONFIG_DISABLED: 'matterbridge_config_disabled',
		MATTERBRIDGE_CONFIG_EDITED: 'matterbridge_config_edited',
		MATTERBRIDGE_CONFIG_ENABLED: 'matterbridge_config_enabled',
		MATTERBRIDGE_CONFIG_REMOVED: 'matterbridge_config_removed',
		MESSAGE_DELETED: 'message_deleted',
		MESSAGE_EDITED: 'message_edited',
		MESSAGE_EXPIRATION_DISABLED: 'message_expiration_disabled',
		MESSAGE_EXPIRATION_ENABLED: 'message_expiration_enabled',
		MODERATOR_DEMOTED: 'moderator_demoted',
		MODERATOR_PROMOTED: 'moderator_promoted',
		OBJECT_SHARED: 'object_shared',
		PASSWORD_REMOVED: 'password_removed',
		PASSWORD_SET: 'password_set',
		PHONE_ADDED: 'phone_added',
		PHONE_REMOVED: 'phone_removed',
		POLL_CLOSED: 'poll_closed',
		POLL_VOTED: 'poll_voted',
		REACTION: 'reaction',
		REACTION_DELETED: 'reaction_deleted',
		REACTION_REVOKED: 'reaction_revoked',
		READ_ONLY: 'read_only',
		READ_ONLY_OFF: 'read_only_off',
		RECORDING_FAILED: 'recording_failed',
		RECORDING_STARTED: 'recording_started',
		RECORDING_STOPPED: 'recording_stopped',
		THREAD_CREATED: 'thread_created',
		THREAD_RENAMED: 'thread_renamed',
		USER_ADDED: 'user_added',
		USER_REMOVED: 'user_removed',
	},

	TYPE: {
		COMMENT: 'comment',
		SYSTEM: 'system',
		OBJECT_SHARED: 'object_shared',
		COMMAND: 'command',
		COMMENT_DELETED: 'comment_deleted',
		VOICE_MESSAGE: 'voice-message',
		RECORD_AUDIO: 'record-audio',
		RECORD_VIDEO: 'record-video',
	},
} as const

export const PARTICIPANT = {
	CALL_FLAG: {
		DISCONNECTED: 0,
		IN_CALL: 1,
		WITH_AUDIO: 2,
		WITH_VIDEO: 4,
		WITH_PHONE: 8,
	},

	SIP_FLAG: {
		MUTE_MICROPHONE: 1,
		MUTE_SPEAKER: 2,
		SPEAKING: 4,
		RAISE_HAND: 8,
	},

	SIP_DIALOUT_FLAG: {
		NONE: 0,
		MUTE_MICROPHONE: 1,
		MUTE_SPEAKER: 2,
	},

	NOTIFY: {
		DEFAULT: 0,
		ALWAYS: 1,
		MENTION: 2,
		NEVER: 3,
	},

	NOTIFY_CALLS: {
		OFF: 0,
		ON: 1,
	},

	TYPE: {
		OWNER: 1,
		MODERATOR: 2,
		USER: 3,
		GUEST: 4,
		USER_SELF_JOINED: 5,
		GUEST_MODERATOR: 6,
	},

	PERMISSIONS: {
		DEFAULT: 0,
		CUSTOM: 1,
		CALL_START: 2,
		CALL_JOIN: 4,
		LOBBY_IGNORE: 8,
		PUBLISH_AUDIO: 16,
		PUBLISH_VIDEO: 32,
		PUBLISH_SCREEN: 64,
		CHAT: 128,
		MAX_DEFAULT: 254,
		MAX_CUSTOM: 255,
	},
} as const

export const SHARED_ITEM = {
	TYPES: {
		AUDIO: 'audio',
		DECK_CARD: 'deckcard',
		FILE: 'file',
		LOCATION: 'location',
		MEDIA: 'media',
		OTHER: 'other',
		POLL: 'poll',
		RECORDING: 'recording',
		VOICE: 'voice',
	},
	MEDIA_ALLOWED_PREVIEW: [
		'image/gif',
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/webp',
	],
} as const

export const WEBINAR = {
	LOBBY: {
		NONE: 0,
		NON_MODERATORS: 1,
	},

	SIP: {
		DISABLED: 0,
		ENABLED: 1,
		ENABLED_NO_PIN: 2,
	},
} as const

export const SHARE = {
	TYPE: {
		USER: 0,
		GROUP: 1,
		EMAIL: 4,
		REMOTE: 6,
		CIRCLE: 7,
		// From OC.Share.SHARE_TYPE_*, can be used by external API
		LINK: 3,
		GUEST: 8,
		REMOTE_GROUP: 9,
		ROOM: 10,
		DECK: 12,
		FEDERATED_GROUP: 14,
		SCIENCEMESH: 15,
	},
} as const

export const FLOW = {
	MESSAGE_MODES: {
		NO_MENTION: 1,
		SELF_MENTION: 2,
		ROOM_MENTION: 3,
	},
} as const

export const POLL = {
	STATUS: {
		OPEN: 0,
		CLOSED: 1,
		DRAFT: 2,
	},

	MODE: {
		PUBLIC: 0,
		HIDDEN: 1,
	},

	ANSWER_TYPE: {
		MULTIPLE: 0,
		SINGLE: 1,
	},
} as const

export const PRIVACY = {
	PUBLIC: 0,
	PRIVATE: 1,
} as const

export const SIMULCAST = {
	LOW: 0,
	MEDIUM: 1,
	HIGH: 2,
} as const

export const VIRTUAL_BACKGROUND = {
	BACKGROUND_TYPE: {
		BLUR: 'blur',
		IMAGE: 'image',
		VIDEO: 'video',
		VIDEO_STREAM: 'video-stream',
	},
	BLUR_STRENGTH: {
		DEFAULT: 10,
	},
} as const

export const BOT = {
	STATE: {
		DISABLED: 0,
		ENABLED: 1,
		NO_SETUP: 2,
	},
} as const

export const AVATAR = {
	SIZE: {
		EXTRA_SMALL: 22,
		COMPACT: 24,
		SMALL: 32,
		DEFAULT: 40,
		MEDIUM: 64,
		LARGE: 128,
		EXTRA_LARGE: 180,
		FULL: 512,
	},
} as const

export const FEDERATION = {
	STATE: {
		PENDING: 0,
		ACCEPTED: 1,
	},
} as const

export const MENTION = {
	TYPE: {
		CALL: 'call',
		USER: 'user',
		GUEST: 'guest',
		EMAIL: 'email',
		USERGROUP: 'user-group',
		CIRCLE: 'circle',
		// Parsed to another types
		FEDERATED_USER: 'federated_user',
		GROUP: 'group',
		TEAM: 'team',
	},
}

/**
 * Task statuses for OCP\TaskProcessing
 */
export const TASK_PROCESSING = {
	STATUS: {
		CANCELLED: 'STATUS_CANCELLED',
		FAILED: 'STATUS_FAILED',
		SUCCESSFUL: 'STATUS_SUCCESSFUL',
		RUNNING: 'STATUS_RUNNING',
		SCHEDULED: 'STATUS_SCHEDULED',
		UNKNOWN: 'STATUS_UNKNOWN',
	},
}
