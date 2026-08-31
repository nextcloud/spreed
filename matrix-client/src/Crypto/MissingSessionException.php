<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/** We do not (yet) have the Megolm session for an event. */
class MissingSessionException extends CryptoException {
	public function __construct(
		public readonly string $roomId,
		public readonly string $sessionId,
		public readonly string $senderKey,
		string $message = 'Unknown Megolm session',
	) {
		parent::__construct($message);
	}
}
