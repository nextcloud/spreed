<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

class Webinary {
	public const LOBBY_NONE = 0;
	public const LOBBY_NON_MODERATORS = 1;

	public const SIP_DISABLED = 0;
	public const SIP_ENABLED = 1;
	public const SIP_ENABLED_NO_PIN = 2;
}
