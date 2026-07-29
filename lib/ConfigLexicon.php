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
			new Entry(Config::STUN_SERVERS, ValueType::ARRAY, [Config::DEFAULT_STUN_SERVER], definition: 'List of STUN servers for WebRTC connections', flags: IAppConfig::FLAG_SENSITIVE),
			new Entry(Config::TURN_SERVERS, ValueType::ARRAY, [], definition: 'List of TURN servers for WebRTC connections', flags: IAppConfig::FLAG_SENSITIVE),
			new Entry(Config::ALLOWED_GROUPS_TALK, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to use Talk'),
			new Entry(Config::ALLOWED_GROUPS_SIP, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to enable SIP dial-in in a conversation'),
			new Entry(Config::ALLOWED_GROUPS_CONVERSATIONS, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to create conversation'),
			new Entry(Config::BREAKOUT_ROOMS_ENABLED, ValueType::BOOL, true, definition: 'Whether or not breakout rooms are allowed (Will only prevent creating new breakout rooms. Existing conversations are not modified.'),
			new Entry(Config::CONVERSATION_SUBFOLDERS, ValueType::BOOL, true, definition: ''),
			new Entry(Config::DEFAULT_ROOM_PERMISSIONS, ValueType::INT, 246, definition: 'Default permissions for non-moderators (see [constants list](constants.md#attendee-permissions) for bit flags)'),
			new Entry(Config::DEFAULT_ATTACHMENT_FOLDER, ValueType::STRING, '/Talk', definition: 'Specify default attachment folder location'),
			new Entry(Config::GRID_VIDEOS_LIMIT, ValueType::INT, 19 /* 5*4 - self */, definition: 'Maximum number of videos to show (additional to the own video)'),
			new Entry(Config::GRID_VIDEOS_LIMIT_ENFORCED, ValueType::BOOL, false, definition: 'Whether the number of grid videos should be enforced'),
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
