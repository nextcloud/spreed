<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace GuzzleHttp\Exception;

class ServerException extends \RuntimeException {

	public function getResponse(): \Psr\Http\Message\ResponseInterface {
	}
	public function hasResponse(): bool {
	}
}
