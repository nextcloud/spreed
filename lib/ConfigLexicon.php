<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

use OCA\Talk\Settings\UserPreference;
use OCA\Talk\Signaling\Manager as SignalingManager;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;
use OCP\IAppConfig;

class ConfigLexicon implements ILexicon {
	#[\Override]
	public function getStrictness(): Strictness {
		// Ignore for now as we only start
		return Strictness::IGNORE;
	}

	#[\Override]
	public function getAppConfigs(): array {
		return [
			new Entry(UserPreference::CONVERSATIONS_LIST_STYLE, ValueType::STRING, UserPreference::CONVERSATION_LIST_STYLE_TWO_LINES),
			new Entry(UserPreference::CHAT_STYLE, ValueType::STRING, UserPreference::CHAT_STYLE_SPLIT),
			new Entry(SignalingManager::HAS_FEATURE_CHANGED_USERS, ValueType::BOOL, false),
			new Entry(Config::RETENTION_CLASSIFIED_ROOMS, ValueType::INT, 3600, definition: 'Retention period of classified conversations in seconds after a call happened (`0` means no-retention)'),
			new Entry(Config::STUN_SERVERS, ValueType::ARRAY, [Config::DEFAULT_STUN_SERVER], definition: 'List of STUN servers for WebRTC connections', flags: IAppConfig::FLAG_SENSITIVE),
			new Entry(Config::TURN_SERVERS, ValueType::ARRAY, [], definition: 'List of TURN servers for WebRTC connections', flags: IAppConfig::FLAG_SENSITIVE),
			new Entry(Config::ALLOWED_GROUPS_TALK, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to use Talk'),
			new Entry(Config::ALLOWED_GROUPS_SIP, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to enable SIP dial-in in a conversation'),
			new Entry(Config::ALLOWED_GROUPS_CONVERSATIONS, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to create conversation'),
			new Entry(Config::BREAKOUT_ROOMS_ENABLED, ValueType::BOOL, true, definition: 'Whether or not breakout rooms are allowed (Will only prevent creating new breakout rooms. Existing conversations are not modified.'),
			new Entry(Config::CONVERSATION_SUBFOLDERS, ValueType::BOOL, true, definition: ''),
			new Entry(Config::DEFAULT_ROOM_PERMISSIONS, ValueType::INT, 246, definition: 'Default permissions for non-moderators' . PHP_EOL . '(see https://github.com/nextcloud/spreed/blob/main/docs/constants.md#attendee-permissions for bit flags)'),
			new Entry(Config::DEFAULT_ATTACHMENT_FOLDER, ValueType::STRING, '/Talk', definition: 'Specify default attachment folder location'),
			new Entry(Config::GRID_VIDEOS_LIMIT, ValueType::INT, 19 /* 5*4 - self */, definition: 'Maximum number of videos to show (additional to the own video)'),
			new Entry(Config::GRID_VIDEOS_LIMIT_ENFORCED, ValueType::BOOL, false, definition: 'Whether the number of grid videos should be enforced'),
			new Entry(Config::GUESTS_PLAY_SOUNDS, ValueType::BOOL, true, definition: 'Whether guests hear the join and leave sounds by default'),
			new Entry(Config::GROUP_CHATS_FORCE_PASSWORDS_ENABLED, ValueType::BOOL, false, definition: 'Whether public chats are forced to use a password'),
			new Entry(Config::EXTERNAL_CALL_SERVICE, ValueType::STRING, '', definition: 'URL of the external service endpoint. `{meetingId}` is replaced with the conversation\'s `objectId` when Talk makes the request'),
			new Entry(Config::EXTERNAL_CALL_SERVICE_SHARED_SECRET, ValueType::STRING, '', definition: 'Shared secret used for two purposes:' . PHP_EOL . 'as the HTTP Basic Auth password when Talk calls the external service, and as the bearer token when the external service calls Talk.' . PHP_EOL . 'Minimum 64 characters, `a-zA-Z0-9` recommended'),
			new Entry(Config::EXTERNAL_CALL_SERVICE_AUTH_USER, ValueType::STRING, '', definition: 'HTTP Basic Auth username used when Talk calls the external service'),
			new Entry(Config::EXTERNAL_CALL_SERVICE_AUTH_PASSWORD, ValueType::STRING, '', definition: 'HTTP Basic Auth password used when Talk calls the external service'),
			new Entry(Config::EXTERNAL_CALL_SERVICE_FRAME_ORIGINS, ValueType::ARRAY, [], definition: 'JSON array of scheme+host(+port) origins that may be loaded in the iframe.' . PHP_EOL . 'Added to `Content-Security-Policy: frame-src` and the `Permissions-Policy` for camera/microphone'),
			new Entry(Config::EXTERNAL_CALL_SERVICE_IFRAME_FIELD, ValueType::STRING, '', definition: 'JSON field name in the external service response that contains the iframe URL'),
			new Entry(Config::CALLS_START_WITHOUT_MEDIA, ValueType::BOOL, false, definition: 'Whether participants start with enabled or disabled audio and video by default'),
			new Entry(Config::INACTIVITY_LOCK_AFTER_DAYS, ValueType::INT, 0, definition: 'A duration (in days) after which rooms are locked. Calculated from the last activity in the room,'),
			new Entry(Config::INACTIVITY_ENABLE_LOBBY, ValueType::BOOL, false, definition: 'Additionally enable the lobby for inactive rooms so they can only be read by moderators.'),
			new Entry(Config::EXPERIMENTS_USERS, ValueType::INT, 0, definition: 'Bit flag of experiments that should be enabled for logged-in users on this server' . PHP_EOL . 'See https://github.com/nextcloud/spreed/blob/main/docs/settings.md#experiments'),
			new Entry(Config::EXPERIMENTS_GUESTS, ValueType::INT, 0, definition: 'Bit flag of experiments that should be enabled for guests on this server' . PHP_EOL . 'See https://github.com/nextcloud/spreed/blob/main/docs/settings.md#experiments'),
			new Entry(Config::CALL_END_TO_END_ENCRYPTION, ValueType::BOOL, false, definition: 'Whether clients should end-to-end encrypt streams in calls (Only supported with High-performance backend'),
		];
	}

	#[\Override]
	public function getUserConfigs(): array {
		return [
			new Entry(UserPreference::PLAY_SOUNDS, ValueType::BOOL, true),
			new Entry(UserPreference::CHAT_STYLE, ValueType::STRING, UserPreference::CHAT_STYLE_SPLIT),
		];
	}
}
