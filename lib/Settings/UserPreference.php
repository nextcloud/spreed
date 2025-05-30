<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Settings;

class UserPreference {
	public const ATTACHMENT_FOLDER = 'attachment_folder';
	public const BLUR_VIRTUAL_BACKGROUND = 'blur_virtual_background';
	public const CALLS_START_WITHOUT_MEDIA = 'calls_start_without_media';
	public const CONVERSATIONS_LIST_STYLE = 'conversations_list_style';
	public const PLAY_SOUNDS = 'play_sounds';
	public const TYPING_PRIVACY = 'typing_privacy';
	public const READ_STATUS_PRIVACY = 'read_status_privacy';
	public const RECORDING_FOLDER = 'recording_folder';

	public const CONVERSATION_LIST_STYLE_TWO_LINES = 'two-lines';
	public const CONVERSATION_LIST_STYLE_COMPACT = 'compact';
}
