<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Talk\Exceptions;

class DialOutFailedException extends \RuntimeException {
	public function __construct(
		string $errorCode,
		protected string $readableError,
	) {
		parent::__construct($errorCode);
	}

	public function getReadableError(): string {
		return $this->readableError;
	}
}
