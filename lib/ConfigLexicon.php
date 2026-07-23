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
			new Entry(Config::ALLOWED_GROUPS_TALK, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to use Talk'),
			new Entry(Config::ALLOWED_GROUPS_SIP, ValueType::ARRAY, [], definition: 'List of group ids that are allowed to enable SIP dial-in in a conversation'),
			new Entry(Config::ALLOWED_GROUPS_FEDERATION, ValueType::ARRAY, [], definition: 'List of local group ids that are allowed to use federated features'),
			new Entry(Config::FEDERATION_ENABLED, ValueType::BOOL, false, definition: 'Whether or not federation with this instance is allowed'),
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
