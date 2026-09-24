<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

/**
 * An outgoing Matrix operation failed; carries the HTTP status the OCS API should answer with.
 */
class MatrixSendException extends \RuntimeException {
	public function __construct(
		string $error,
		private readonly int $status,
		?\Throwable $previous = null,
	) {
		parent::__construct($error, $status, $previous);
	}

	public function getStatus(): int {
		return $this->status;
	}

	public function getError(): string {
		return $this->getMessage();
	}
}
