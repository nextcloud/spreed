<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions;

class RemoteClientException extends \Exception {
	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		protected array $responseData = [],
	) {
		parent::__construct($message, $code, $previous);
	}

	public function getResponseData(): array {
		return $this->responseData;
	}
}
