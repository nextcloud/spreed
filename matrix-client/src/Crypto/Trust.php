<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

final class Trust {
	public const UNKNOWN = 0;
	public const CROSS_SIGNED = 1;
	public const VERIFIED = 2;
	public const BLOCKED = 3;
}
