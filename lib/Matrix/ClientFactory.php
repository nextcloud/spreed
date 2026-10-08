<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix;

use GuzzleHttp\Psr7\HttpFactory;
use OCA\Talk\Matrix\Adapter\HttpClient;
use OCA\Talk\Matrix\Client\Discovery;
use OCP\Http\Client\IClientService;

/**
 * Builds library clients on top of Nextcloud's HTTP client.
 */
class ClientFactory {
	public function __construct(
		private readonly IClientService $clientService,
	) {
	}

	public function discovery(): Discovery {
		return new Discovery(new HttpClient($this->clientService, 15), new HttpFactory());
	}
}
