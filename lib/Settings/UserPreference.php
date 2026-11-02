<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Settings;

use OCA\Talk\Config;

class UserPreference {
	public const string ATTACHMENT_FOLDER = 'attachment_folder';
	public const string BLUR_VIRTUAL_BACKGROUND = 'blur_virtual_background';
	public const string CALLS_START_WITHOUT_MEDIA = 'calls_start_without_media';
	public const string CONVERSATIONS_LIST_STYLE = 'conversations_list_style';
	public const string CHAT_STYLE = 'chat_style';
	public const string PLAY_SOUNDS = 'play_sounds';
	public const string TYPING_PRIVACY = 'typing_privacy';
	public const string READ_STATUS_PRIVACY = 'read_status_privacy';
	public const string RECORDING_FOLDER = 'recording_folder';

	public const string CONVERSATIONS_LIST_STYLE_TWO_LINES = 'two-lines';
	public const string CONVERSATIONS_LIST_STYLE_COMPACT = 'compact';

	public const string CHAT_STYLE_SPLIT = 'split';
	public const string CHAT_STYLE_UNIFIED = 'unified';

	public const string CONVERSATIONS_SORT_ORDER = 'conversations_sort_order';
	public const string CONVERSATIONS_SORT_ORDER_ACTIVITY = 'activity';
	public const string CONVERSATIONS_SORT_ORDER_ALPHABETICAL = 'alphabetical';

	public const string CONVERSATIONS_GROUP_MODE = 'conversations_group_mode';
	public const string CONVERSATIONS_GROUP_MODE_NONE = 'none';
	public const string CONVERSATIONS_GROUP_MODE_GROUP_FIRST = 'group-first';
	public const string CONVERSATIONS_GROUP_MODE_PRIVATE_FIRST = 'private-first';

	public const string CONVERSATIONS_UNARCHIVE = 'conversations_unarchive';
	public const string CONVERSATIONS_UNARCHIVE_NEVER = 'never';
	public const string CONVERSATIONS_UNARCHIVE_MENTION = 'mention';
	public const string CONVERSATIONS_UNARCHIVE_ALWAYS = 'always';

	public const string CONVERSATIONS_TAGS_SHOW_UNREAD = 'conversations_tags_show_unread';
	public const string CONVERSATIONS_TAGS_SHOW_UNREAD_NEVER = 'never';
	public const string CONVERSATIONS_TAGS_SHOW_UNREAD_MENTION = 'mention';
	public const string CONVERSATIONS_TAGS_SHOW_UNREAD_ALWAYS = 'always';

	public const string LIVE_TRANSCRIPTION_TARGET_LANGUAGE_ID = 'live_transcription_target_language_id';

	public const string NOTE_TO_SELF = 'note_to_self';
	public const string CHANGELOG = Config::CHANGELOG;
	public const string SAMPLES_CREATED = 'samples_created';
	public const string SIGNALING_TICKET_SECRET = Config::SIGNALING_TICKET_SECRET;

}
