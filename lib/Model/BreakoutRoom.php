<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

class BreakoutRoom {
	public const MODE_NOT_CONFIGURED = 0;
	public const MODE_AUTOMATIC = 1;
	public const MODE_MANUAL = 2;
	public const MODE_FREE = 3;

	public const STATUS_STOPPED = 0;
	public const STATUS_STARTED = 1;
	public const STATUS_ASSISTANCE_RESET = 0;
	public const STATUS_ASSISTANCE_REQUESTED = 2;

	public const MINIMUM_ROOM_AMOUNT = 1;
	public const MAXIMUM_ROOM_AMOUNT = 20;

	public const PARENT_OBJECT_TYPE = 'room';
}
