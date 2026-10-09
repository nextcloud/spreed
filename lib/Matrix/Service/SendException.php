<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCP\AppFramework\Http;

/**
 * The change could not be sent to the Matrix room, the message is the error
 * of the API response
 */
class SendException extends \Exception {
	/**
	 * @param Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY $status
	 */
	public function __construct(
		string $error,
		private readonly int $status,
		?\Throwable $previous = null,
	) {
		parent::__construct($error, 0, $previous);
	}

	/**
	 * @return Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY
	 */
	public function getStatus(): int {
		return $this->status;
	}
}
