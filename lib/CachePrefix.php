<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

class CachePrefix {
	public const FEDERATED_PCM = 'talk/pcm/';
	public const CHAT_LAST_MESSAGE_ID = 'talk/lastmsgid';
	public const CHAT_UNREAD_COUNT = 'talk/unreadcount';
	public const SIGNALING_ASSIGNED_SERVER = 'hpb_servers';
}
