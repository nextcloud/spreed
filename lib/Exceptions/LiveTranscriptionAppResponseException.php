<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions;

use OCP\Http\Client\IResponse;

class LiveTranscriptionAppResponseException extends \Exception {

	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		protected IResponse $response,
	) {
		parent::__construct($message, $code, $previous);
	}

	public function getResponse(): IResponse {
		return $this->response;
	}
}
