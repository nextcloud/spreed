/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
export const SIGNALING = {
	MODE: {
		INTERNAL: 'internal',
		EXTERNAL: 'external',
		CLUSTER_CONVERSATION: 'conversation_cluster',
	},
}
export const CHAT = {
	FETCH_LIMIT: 100,
	MINIMUM_VISIBLE: 5,
}

export const CALL = {
	RECORDING: {
		OFF: 0,
		VIDEO: 1,
		AUDIO: 2,
		VIDEO_STARTING: 3,
		AUDIO_STARTING: 4,
		FAILED: 5,
	},
}

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

	TYPE: {
		ONE_TO_ONE: 1,
		GROUP: 2,
		PUBLIC: 3,
		CHANGELOG: 4,
		ONE_TO_ONE_FORMER: 5,
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

	MAX_NAME_LENGTH: 255,
	MAX_DESCRIPTION_LENGTH: 500,
}
export const ATTENDEE = {
	ACTOR_TYPE: {
		USERS: 'users',
		GUESTS: 'guests',
		EMAILS: 'emails',
		GROUPS: 'groups',
		CIRCLES: 'circles',
		BOTS: 'bots',
		BRIDGED: 'bridged',
	},

	BRIDGE_BOT_ID: 'bridge-bot',

	CHANGELOG_BOT_ID: 'changelog',
}
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
}

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
}

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
}

export const SHARE = {
	TYPE: {
		USER: 0,
		GROUP: 1,
		EMAIL: 4,
		REMOTE: 6,
		CIRCLE: 7,
	},
}

export const FLOW = {
	MESSAGE_MODES: {
		NO_MENTION: 1,
		SELF_MENTION: 2,
		ROOM_MENTION: 3,
	},
}

export const PRIVACY = {
	PUBLIC: 0,
	PRIVATE: 1,
}

export const SIMULCAST = {
	LOW: 0,
	MEDIUM: 1,
	HIGH: 2,
}

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
}

export const BOT = {
	STATE: {
		DISABLED: 0,
		ENABLED: 1,
		NO_SETUP: 2,
	},
}

export const AVATAR = {
	SIZE: {
		EXTRA_SMALL: 22,
		SMALL: 32,
		DEFAULT: 44,
		MEDIUM: 64,
		LARGE: 128,
		EXTRA_LARGE: 180,
		FULL: 512,
	},
}
