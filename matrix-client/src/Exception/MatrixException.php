<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Exception;

/**
 * Error returned by a homeserver (standard error response with errcode/error).
 */
class MatrixException extends \RuntimeException {
	public function __construct(
		string $message,
		private readonly int $httpStatus = 0,
		private readonly string $errcode = '',
		private readonly array $body = [],
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $httpStatus, $previous);
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}

	public function getErrcode(): string {
		return $this->errcode;
	}

	public function getBody(): array {
		return $this->body;
	}
}
